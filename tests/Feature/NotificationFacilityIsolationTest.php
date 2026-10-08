<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class NotificationFacilityIsolationTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    private function makeUser(string $role, ?Facility $facility): User
    {
        return User::factory()->create([
            'facility_id' => $facility?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function notify(User $recipient, ?int $facilityId, string $type = 'incident_reported'): void
    {
        Notification::create([
            'user_id' => $recipient->id,
            'facility_id' => $facilityId,
            'type' => $type,
            'title' => 'Test',
            'message' => 'Test',
        ]);
    }

    public function test_admin_of_other_facility_does_not_receive_notification(): void
    {
        $other = Facility::factory()->create();
        Branch::factory()->create(['facility_id' => $other->id]);
        $outsider = $this->makeUser('administrator', $other);
        $insider = $this->makeUser('administrator', $this->facility);

        $this->notify($outsider, $this->facility->id);
        $this->notify($insider, $this->facility->id);

        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $insider->id, 'facility_id' => $this->facility->id]);
    }

    public function test_missing_facility_is_filled_from_recipient(): void
    {
        $user = $this->makeUser('caregiver', $this->facility);

        $this->notify($user, null, 'reminder');

        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'facility_id' => $this->facility->id]);
    }

    public function test_super_admin_only_receives_platform_notifications(): void
    {
        $superAdmin = $this->makeUser('super_admin', null);

        $this->notify($superAdmin, $this->facility->id, 'medication_administered');
        $this->notify($superAdmin, $this->facility->id, 'facility_created');

        $this->assertDatabaseMissing('notifications', ['user_id' => $superAdmin->id, 'type' => 'medication_administered']);
        $this->assertDatabaseHas('notifications', ['user_id' => $superAdmin->id, 'type' => 'facility_created']);
    }

    public function test_email_preferences_reject_staff_of_other_facility(): void
    {
        $other = Facility::factory()->create();
        $outsider = $this->makeUser('administrator', $other);

        $this->assertFalse(
            app(EmailPreferenceService::class)->shouldSendEmail($outsider, 'incident', $this->facility)
        );
    }
}
