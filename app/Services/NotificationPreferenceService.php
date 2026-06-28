<?php

namespace App\Services;

use App\Models\FacilitySetting;

/**
 * Decides whether a per-facility notification should actually be sent, based on
 * the super-admin Notification Settings (FacilitySetting rows).
 *
 * Two layers gate each of the three configurable EVENTS:
 *   - the event flag    (notify_on_incident / notify_on_check_in_out / notify_on_resident_sign_out)
 *   - the channel flag   (enable_email_notifications / enable_in_app_notifications)
 *
 * A notification on a channel is allowed only when BOTH its event flag and its
 * channel flag are on. Missing flags default to ON so facilities that never
 * touched the settings keep today's always-notify behaviour. A null facility
 * (platform/super-admin scope) is never blocked. Clinical notifications
 * (medications, vitals, appointments) do NOT go through this gate.
 */
class NotificationPreferenceService
{
    /** @var array<int, array<string, string>> per-request cache: facilityId => [key => value] */
    private array $cache = [];

    private const EVENT_SETTING = [
        'incident' => 'notify_on_incident',
        'check_in_out' => 'notify_on_check_in_out',
        'resident_sign_out' => 'notify_on_resident_sign_out',
    ];

    public function shouldSendEmail(?int $facilityId, string $eventKey): bool
    {
        return $this->shouldSend($facilityId, $eventKey, 'enable_email_notifications');
    }

    public function shouldSendInApp(?int $facilityId, string $eventKey): bool
    {
        return $this->shouldSend($facilityId, $eventKey, 'enable_in_app_notifications');
    }

    private function shouldSend(?int $facilityId, string $eventKey, string $channelSettingKey): bool
    {
        // Platform-scope notifications (no facility) are never gated here.
        if ($facilityId === null) {
            return true;
        }

        $eventSettingKey = self::EVENT_SETTING[$eventKey]
            ?? throw new \InvalidArgumentException("Unknown notification event key: {$eventKey}");

        return $this->flag($facilityId, $eventSettingKey)
            && $this->flag($facilityId, $channelSettingKey);
    }

    /** Read a boolean facility setting; absent = ON (preserve legacy always-notify). */
    private function flag(int $facilityId, string $key): bool
    {
        $settings = $this->cache[$facilityId] ??= FacilitySetting::query()
            ->where('facility_id', $facilityId)
            ->pluck('value', 'key')
            ->all();

        if (! array_key_exists($key, $settings)) {
            return true;
        }

        return filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
    }
}
