<?php

namespace Tests\Feature;

use App\Mail\IncidentNotification;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\EmailPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
    /** Facility B admin and a super admin alongside the facility A admin under test. */
    private function makeOutsiders(): array
    {
        $other = Facility::factory()->create();
        Branch::factory()->create(['facility_id' => $other->id]);

        return [$this->makeUser('administrator', $other), $this->makeUser('super_admin', null)];
    }

    public function test_incident_alerts_only_reach_own_facility(): void
    {
        Mail::fake();
        [$outsider, $superAdmin] = $this->makeOutsiders();
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();

        Incident::create([
            'incident_number' => 'INC-ISO',
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'incident_type' => 'Fall',
            'description' => 'Test incident.',
            'incident_date' => now(),
            'severity' => 'low',
            'priority' => 'low',
            'status' => 'open',
            'reported_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'incident_reported']);
        $this->assertSame(0, Notification::withoutGlobalScopes()->whereIn('user_id', [$outsider->id, $superAdmin->id])->count());
    }

    public function test_vital_sign_alerts_only_reach_own_facility(): void
    {
        Mail::fake();
        [$outsider, $superAdmin] = $this->makeOutsiders();
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();

        VitalSign::withoutGlobalScopes()->create([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'taken_by' => $admin->id,
            'measurement_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id]);
        $this->assertSame(0, Notification::withoutGlobalScopes()->whereIn('user_id', [$outsider->id, $superAdmin->id])->count());
    }

    public function test_facility_recipients_scope_excludes_other_facilities_and_super_admins(): void
    {
        [$outsider, $superAdmin] = $this->makeOutsiders();
        $admin = $this->makeUser('administrator', $this->facility);

        $ids = User::facilityRecipients($this->facility->id, ['administrator', 'super_admin'])->pluck('id')->all();

        $this->assertSame([$admin->id], $ids);
        $this->assertSame(0, User::facilityRecipients(null)->count());
    }

    public function test_new_facility_alert_goes_to_super_admins_only(): void
    {
        [$outsider, $superAdmin] = $this->makeOutsiders();

        $created = Facility::factory()->create();

        $this->assertDatabaseHas('notifications', ['user_id' => $superAdmin->id, 'type' => 'facility_created', 'facility_id' => $created->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id, 'type' => 'facility_created']);
    }

    public function test_purge_migration_removes_leaked_notifications(): void
    {
        [$outsider, $superAdmin] = $this->makeOutsiders();
        $admin = $this->makeUser('administrator', $this->facility);

        $row = fn (User $u, string $type) => [
            'user_id' => $u->id, 'facility_id' => $this->facility->id, 'type' => $type,
            'title' => 'T', 'message' => 'M', 'is_read' => false, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('notifications')->delete();
        DB::table('notifications')->insert([
            $row($admin, 'incident_reported'),
            $row($outsider, 'incident_reported'),
            $row($superAdmin, 'vital_recorded'),
            $row($superAdmin, 'facility_created'),
        ]);

        (require database_path('migrations/2026_10_08_000001_purge_cross_facility_notifications.php'))->up();

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $superAdmin->id, 'type' => 'vital_recorded']);
        $this->assertDatabaseHas('notifications', ['user_id' => $superAdmin->id, 'type' => 'facility_created']);
    }
    public function test_submitting_an_incident_emails_each_admin_once(): void
    {
        Mail::fake();
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();

        $this->postJson('/api/v1/incidents', [
            'resident_id' => $resident->id,
            'incident_type' => 'Fall',
            'description' => 'Resident slipped in the hallway.',
            'incident_date' => now()->toDateTimeString(),
            'severity' => 'low',
            'priority' => 'low',
        ])->assertCreated();

        $sent = collect(Mail::sent(IncidentNotification::class))
            ->merge(Mail::queued(IncidentNotification::class))
            ->filter(fn ($mail) => $mail->hasTo($admin->email));

        $this->assertCount(1, $sent);
    }
}
