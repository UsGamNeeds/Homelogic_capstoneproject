<?php

namespace Tests\Feature;

use App\Models\ExpoPushToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class ExpoPushTokenTest extends TestCase
{
    use RefreshDatabase, SetupFacility;

    private const TOKEN = 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createFacilityAndBranch();
    }

    public function test_caregiver_can_register_a_token(): void
    {
        $user = $this->createAndActAs('caregiver');

        $this->postJson('/api/v1/expo-push-tokens', [
            'token' => self::TOKEN,
            'platform' => 'ios',
        ])->assertStatus(201);

        $this->assertDatabaseHas('user_expo_push_tokens', [
            'user_id' => $user->id,
            'token' => self::TOKEN,
            'platform' => 'ios',
        ]);
    }

    public function test_a_shared_device_moves_to_whoever_signs_in(): void
    {
        $first = $this->createAndActAs('caregiver');
        $this->postJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])->assertStatus(201);

        $second = $this->createAndActAs('caregiver');
        $this->postJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])->assertStatus(201);

        $this->assertSame(1, ExpoPushToken::where('token', self::TOKEN)->count());
        $this->assertDatabaseHas('user_expo_push_tokens', ['token' => self::TOKEN, 'user_id' => $second->id]);
        $this->assertDatabaseMissing('user_expo_push_tokens', ['user_id' => $first->id]);
    }

    public function test_rejects_something_that_is_not_an_expo_token(): void
    {
        $this->createAndActAs('caregiver');

        $this->postJson('/api/v1/expo-push-tokens', ['token' => 'not-a-token'])
            ->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])
            ->assertStatus(401);
    }

    public function test_a_user_can_only_remove_their_own_token(): void
    {
        $owner = $this->createAndActAs('caregiver');
        $this->postJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])->assertStatus(201);

        $this->createAndActAs('caregiver');
        $this->deleteJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])->assertOk();
        $this->assertDatabaseHas('user_expo_push_tokens', ['token' => self::TOKEN, 'user_id' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson('/api/v1/expo-push-tokens', ['token' => self::TOKEN])->assertOk();
        $this->assertDatabaseMissing('user_expo_push_tokens', ['token' => self::TOKEN]);
    }

    public function test_a_new_notification_is_pushed_to_the_users_devices(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'abc']]])]);

        $user = $this->createAndActAs('caregiver');
        ExpoPushToken::create(['user_id' => $user->id, 'token' => self::TOKEN, 'platform' => 'android']);

        $notification = $this->notify($user);

        Http::assertSent(function (Request $request) use ($notification) {
            $message = $request->data()[0] ?? [];

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && $message['to'] === self::TOKEN
                && $message['title'] === 'Incident reported'
                && $message['data']['notificationId'] === $notification->id;
        });
        $this->assertDatabaseHas('user_expo_push_tokens', ['token' => self::TOKEN]);
    }

    public function test_an_uninstalled_device_is_forgotten(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [[
            'status' => 'error',
            'message' => 'not a registered push notification recipient',
            'details' => ['error' => 'DeviceNotRegistered'],
        ]]])]);

        $user = $this->createAndActAs('caregiver');
        ExpoPushToken::create(['user_id' => $user->id, 'token' => self::TOKEN]);

        $this->notify($user);

        $this->assertDatabaseMissing('user_expo_push_tokens', ['token' => self::TOKEN]);
    }

    public function test_users_without_a_device_cause_no_request(): void
    {
        Http::fake();

        $this->notify($this->createAndActAs('caregiver'));

        Http::assertNothingSent();
    }

    private function notify(User $user): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'facility_id' => $this->facility->id,
            'branch_id' => $this->branch->id,
            'type' => 'incident',
            'title' => 'Incident reported',
            'message' => 'A fall was reported.',
            'icon' => 'alert',
            'icon_color' => 'danger',
            'is_read' => false,
            'action_url' => '/incidents',
        ]);
    }
}
