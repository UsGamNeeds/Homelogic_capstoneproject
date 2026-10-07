<?php

namespace App\Services;

use App\Models\ExpoPushToken;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription as WebPushSubscription;

class PushNotificationService
{
    /**
     * What a phone shows in place of the notification's message unless
     * EXPO_PUSH_SHOW_DETAILS is on. Notification titles are generic ("New
     * Incident Reported"); the messages name residents.
     */
    public const EXPO_GENERIC_BODY = 'Open HomeLogic360 to view details.';

    protected ?WebPush $webPush = null;

    public function __construct()
    {
        $publicKey = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');
        $subject = config('webpush.vapid.subject', 'mailto:admin@' . parse_url(config('app.url'), PHP_URL_HOST));

        if ($publicKey && $privateKey) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
        }
    }

    /**
     * Send a push notification to a user (all their registered devices).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        // The mobile app goes through Expo, which needs no VAPID keys, so it is
        // sent before the web-push early returns below.
        $this->sendToExpo($user, $title, $body, $data);

        if (!$this->webPush) {
            Log::debug('[PushNotification] VAPID not configured, skipping push');
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            Log::debug("[PushNotification] No subscriptions for user {$user->id}");
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $data['icon'] ?? '/images/logonew.png',
            'badge' => $data['badge'] ?? '/images/logonew.png',
            'tag' => $data['tag'] ?? 'notification',
            'data' => array_merge($data['data'] ?? [], ['url' => $data['url'] ?? '/']),
        ]);

        foreach ($subscriptions as $model) {
            $this->sendToOne($model, $payload);
        }
    }

    /**
     * Send push payload for an in-app Notification model (used by observer).
     */
    public function sendForNotification(\App\Models\Notification $notification): void
    {
        $user = $notification->user;
        if (!$user) {
            return;
        }

        $actionUrl = $notification->action_url ?? '/notifications';
        $this->sendToUser($user, $notification->title, $notification->message, [
            'tag' => 'notification-' . $notification->id,
            'url' => $actionUrl,
            'data' => [
                'notificationId' => $notification->id,
                'url' => $actionUrl,
            ],
        ]);
    }

    /**
     * Send to the user's mobile devices through Expo's push service.
     *
     * Expo answers with one ticket per message, in order. A ticket reporting
     * DeviceNotRegistered means the app was uninstalled or the token rotated,
     * so that token is removed, like a 404/410 web-push subscription.
     */
    protected function sendToExpo(User $user, string $title, string $body, array $data): void
    {
        if (!config('services.expo_push.enabled')) {
            return;
        }

        if (!config('services.expo_push.show_details')) {
            $body = self::EXPO_GENERIC_BODY;
        }

        $tokens = ExpoPushToken::where('user_id', $user->id)->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $messages = $tokens->map(fn (ExpoPushToken $token) => [
            'to' => $token->token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'channelId' => 'default',
            'data' => array_merge($data['data'] ?? [], ['url' => $data['url'] ?? '/']),
        ])->values();

        try {
            // Expo accepts at most 100 messages per request.
            foreach ($messages->chunk(100) as $chunk) {
                $chunk = $chunk->values();
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->post('https://exp.host/--/api/v2/push/send', $chunk->all());

                if (!$response->successful()) {
                    Log::warning("[PushNotification] Expo push failed for user {$user->id}: HTTP " . $response->status());
                    continue;
                }

                foreach ($response->json('data', []) as $i => $ticket) {
                    if (($ticket['status'] ?? null) !== 'error') {
                        continue;
                    }
                    if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                        ExpoPushToken::where('token', $chunk[$i]['to'] ?? null)->delete();
                        Log::info("[PushNotification] Removed unregistered Expo token for user {$user->id}");
                    } else {
                        Log::warning("[PushNotification] Expo push error for user {$user->id}: " . ($ticket['message'] ?? 'unknown'));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[PushNotification] Expo send failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }
    }

    /**
     * Send to a single subscription; remove if expired/invalid.
     */
    protected function sendToOne(PushSubscription $model, string $payload): void
    {
        try {
            $subscription = $model->toWebPushSubscription();
            $report = $this->webPush->sendOneNotification($subscription, $payload);

            if ($report->isSuccess()) {
                return;
            }

            // 404/410 = subscription expired or invalid, remove it
            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $model->delete();
                Log::info("[PushNotification] Removed invalid subscription for user {$model->user_id}");
            } else {
                Log::warning("[PushNotification] Push failed for user {$model->user_id}: " . $report->getReason());
            }
        } catch (\Throwable $e) {
            Log::warning('[PushNotification] Send failed: ' . $e->getMessage(), ['subscription_id' => $model->id]);
        }
    }
}
