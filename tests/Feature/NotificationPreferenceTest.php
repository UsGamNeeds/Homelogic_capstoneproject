<?php

namespace Tests\Feature;

use App\Mail\IncidentNotification;
use App\Models\FacilitySetting;
use App\Models\Incident;
use App\Services\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    public function test_preferences_default_to_on_when_unset(): void
    {
        $svc = app(NotificationPreferenceService::class);

        $this->assertTrue($svc->shouldSendInApp($this->facility->id, 'incident'));
        $this->assertTrue($svc->shouldSendEmail($this->facility->id, 'incident'));
        // Platform-scope (no facility) is never gated.
        $this->assertTrue($svc->shouldSendInApp(null, 'incident'));
    }

    public function test_event_flag_off_blocks_both_channels(): void
    {
        $this->setSetting('notify_on_incident', false);
        $svc = app(NotificationPreferenceService::class);

        $this->assertFalse($svc->shouldSendInApp($this->facility->id, 'incident'));
        $this->assertFalse($svc->shouldSendEmail($this->facility->id, 'incident'));
    }

    public function test_channel_flag_off_blocks_only_that_channel(): void
    {
        $this->setSetting('enable_in_app_notifications', false);
        $svc = app(NotificationPreferenceService::class);

        $this->assertFalse($svc->shouldSendInApp($this->facility->id, 'incident'));
        $this->assertTrue($svc->shouldSendEmail($this->facility->id, 'incident'));
    }

    public function test_incident_in_app_notification_suppressed_when_event_disabled(): void
    {
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();
        $this->setSetting('notify_on_incident', false);

        $this->createIncident($resident->id, $admin->id, 'INC-OFF');

        $this->assertDatabaseMissing('notifications', [
            'type' => 'incident_reported',
            'facility_id' => $this->facility->id,
        ]);
    }

    public function test_incident_in_app_notification_sent_when_unset(): void
    {
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();

        $this->createIncident($resident->id, $admin->id, 'INC-ON');

        $this->assertDatabaseHas('notifications', [
            'type' => 'incident_reported',
            'user_id' => $admin->id,
        ]);
    }

    public function test_all_three_event_keys_are_gated_independently(): void
    {
        $this->setSetting('notify_on_check_in_out', false);
        $svc = app(NotificationPreferenceService::class);

        // check_in_out disabled; the other two remain on (unset = on).
        $this->assertFalse($svc->shouldSendInApp($this->facility->id, 'check_in_out'));
        $this->assertTrue($svc->shouldSendInApp($this->facility->id, 'resident_sign_out'));
        $this->assertTrue($svc->shouldSendInApp($this->facility->id, 'incident'));
    }

    public function test_incident_email_not_sent_when_event_disabled(): void
    {
        Mail::fake();
        $admin = $this->createAndActAs('administrator');
        $resident = $this->createResident();
        $this->setSetting('notify_on_incident', false);

        $this->createIncident($resident->id, $admin->id, 'INC-MAIL');

        Mail::assertNotQueued(IncidentNotification::class);
        Mail::assertNotSent(IncidentNotification::class);
    }

    private function createIncident(int $residentId, int $reportedBy, string $number): Incident
    {
        return Incident::create([
            'incident_number' => $number,
            'resident_id' => $residentId,
            'branch_id' => $this->branch->id,
            'incident_type' => 'Fall',
            'description' => 'Test incident.',
            'incident_date' => Carbon::parse('2026-04-15 14:00:00', config('app.timezone')),
            'severity' => 'low',
            'priority' => 'low',
            'status' => 'open',
            'reported_by' => $reportedBy,
        ]);
    }

    private function setSetting(string $key, bool $value): void
    {
        FacilitySetting::create([
            'facility_id' => $this->facility->id,
            'category' => 'notification',
            'key' => $key,
            'value' => $value ? '1' : '0',
            'type' => 'boolean',
        ]);
    }
}
