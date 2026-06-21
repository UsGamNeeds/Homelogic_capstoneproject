<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\FamilyMessage;
use App\Models\Notification;
use App\Models\Reminder;
use App\Models\ResidentSignOut;
use App\Models\Scopes\FacilityScope;
use App\Models\SleepRecord;
use App\Models\StaffClockIn;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * Batch 3 — tenant isolation. These PHI models previously had NO global
 * facility scope, so a logged-in user could read another facility's records
 * by id or via unfiltered listings. Each must now filter by the caller's
 * facility (super admins excepted).
 */
class TenantScopeIsolationTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    /** All PHI models in this batch must register FacilityScope. */
    public function test_all_phi_models_register_facility_scope(): void
    {
        $models = [
            Notification::class,
            Visitor::class,
            Reminder::class,
            ResidentSignOut::class,
            StaffClockIn::class,
            Assessment::class,
            Appointment::class,
            VitalSign::class,
            SleepRecord::class,
        ];

        foreach ($models as $class) {
            $scopes = (new $class)->getGlobalScopes();
            $this->assertArrayHasKey(
                FacilityScope::class,
                $scopes,
                "$class is missing the FacilityScope global scope"
            );
        }
    }

    /** branch_id-based model: VitalSign rows in another facility are hidden. */
    public function test_vital_signs_are_hidden_across_facilities(): void
    {
        [$facilityA, $branchA] = $this->createFacilityAndBranch('Facility A');
        [$facilityB, $branchB] = $this->createFacilityAndBranch('Facility B');

        $residentB = $this->createResident($branchB);
        $takenBy = User::factory()->create([
            'facility_id' => $facilityB->id,
            'assigned_branch_id' => $branchB->id,
            'role' => 'nurse',
        ]);

        VitalSign::withoutGlobalScopes()->create([
            'resident_id' => $residentB->id,
            'branch_id' => $branchB->id,
            'taken_by' => $takenBy->id,
            'measurement_date' => '2026-01-01',
        ]);

        // Act as a user in facility A.
        $this->createAndActAs('administrator', $facilityA, $branchA);

        $this->assertSame(0, VitalSign::count(), 'Facility A must not see facility B vital signs');
        $this->assertSame(1, VitalSign::withoutGlobalScopes()->count(), 'Row should exist unscoped');
    }

    /** facility_id-based model: Notifications in another facility are hidden. */
    public function test_notifications_are_hidden_across_facilities(): void
    {
        [$facilityA, $branchA] = $this->createFacilityAndBranch('Facility A');
        [$facilityB, $branchB] = $this->createFacilityAndBranch('Facility B');

        $userB = User::factory()->create([
            'facility_id' => $facilityB->id,
            'assigned_branch_id' => $branchB->id,
            'role' => 'caregiver',
        ]);

        Notification::withoutGlobalScopes()->create([
            'user_id' => $userB->id,
            'facility_id' => $facilityB->id,
            'type' => 'info',
            'title' => 'Hello B',
            'message' => 'Facility B only',
        ]);

        $this->createAndActAs('administrator', $facilityA, $branchA);

        $this->assertSame(0, Notification::count(), 'Facility A must not see facility B notifications');
        $this->assertSame(1, Notification::withoutGlobalScopes()->count());
    }

    /** Controller fix: staff threads list excludes other facilities' residents. */
    public function test_family_message_threads_exclude_other_facility_residents(): void
    {
        [$facilityA, $branchA] = $this->createFacilityAndBranch('Facility A');
        [$facilityB, $branchB] = $this->createFacilityAndBranch('Facility B');

        $residentA = $this->createResident($branchA);
        $residentB = $this->createResident($branchB);

        foreach ([$residentA, $residentB] as $resident) {
            FamilyMessage::create([
                'resident_id' => $resident->id,
                'sender_type' => 'staff',
                'sender_id' => 1,
                'body' => 'hello',
            ]);
        }

        // Act as staff in facility A.
        $this->createAndActAs('administrator', $facilityA, $branchA);

        $response = $this->getJson('/api/v1/family/messages/threads');
        $response->assertOk();

        $residentIds = collect($response->json('data'))->pluck('resident_id')->all();

        $this->assertContains($residentA->id, $residentIds, 'Own facility resident should appear');
        $this->assertNotContains($residentB->id, $residentIds, 'Other facility resident must NOT leak');
    }
}
