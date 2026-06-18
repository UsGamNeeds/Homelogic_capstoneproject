<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * Guards the Med log date-range filter against the Pacific/UTC boundary skew:
 * administered_at is stored as Pacific wall-clock, so a Carbon boundary that the
 * query layer converts to UTC pulls in rows from the adjacent Pacific day.
 */
class MedicationLogDateFilterTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    public function test_date_range_filter_uses_pacific_day_boundaries(): void
    {
        $user = $this->createAndActAs('administrator');

        $resident = Resident::withoutGlobalScopes()->create([
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'branch_id' => $this->branch->id,
            'date_of_birth' => '1950-01-15',
            'gender' => 'female',
            'admission_date' => '2024-01-01',
            'is_active' => true,
            'status' => 'active',
        ]);

        $medication = Medication::create([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'name' => 'Trazodone 50 Mg Tablet',
            'instructions' => 'p.m',
            'time_1' => '20:00:00',
            'created_by' => $user->id,
            'is_active' => true,
            'start_date' => '2020-01-01',
        ]);

        // Dose at 8:44 PM Pacific on June 14 — belongs to the 14th.
        $june14 = MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'administered_by' => $user->id,
            'administered_at' => Carbon::parse('2026-06-14 20:44:00', config('app.timezone')),
            'status' => 'completed',
            'dosage_given' => 'DOSE-JUNE-14',
        ]);

        // Dose at 3:44 AM Pacific on June 15 — belongs to the 15th, must be excluded.
        $june15 = MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'administered_by' => $user->id,
            'administered_at' => Carbon::parse('2026-06-15 03:44:00', config('app.timezone')),
            'status' => 'completed',
            'dosage_given' => 'DOSE-JUNE-15',
        ]);

        $response = $this->getJson(
            '/api/v1/medication-administrations?resident_id='.$resident->id.'&date_from=2026-06-14&date_to=2026-06-14'
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($june14->id, $ids, 'June 14 (Pacific) dose must be included when filtering the 14th.');
        $this->assertNotContains($june15->id, $ids, 'June 15 (Pacific) dose must be excluded when filtering the 14th.');
    }

    public function test_early_morning_pacific_dose_is_included_in_its_own_day(): void
    {
        $user = $this->createAndActAs('administrator');

        $resident = Resident::withoutGlobalScopes()->create([
            'name' => 'John Roe',
            'first_name' => 'John',
            'last_name' => 'Roe',
            'branch_id' => $this->branch->id,
            'date_of_birth' => '1945-03-10',
            'gender' => 'male',
            'admission_date' => '2024-01-01',
            'is_active' => true,
            'status' => 'active',
        ]);

        $medication = Medication::create([
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'name' => 'Famotidine 20Mg',
            'instructions' => 'b.i.d',
            'time_1' => '03:00:00',
            'created_by' => $user->id,
            'is_active' => true,
            'start_date' => '2020-01-01',
        ]);

        // 3:00 AM Pacific on June 14 — must appear when filtering the 14th
        // (the buggy UTC boundary used to exclude it).
        $earlyJune14 = MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $resident->id,
            'branch_id' => $this->branch->id,
            'administered_by' => $user->id,
            'administered_at' => Carbon::parse('2026-06-14 03:00:00', config('app.timezone')),
            'status' => 'completed',
            'dosage_given' => 'EARLY-JUNE-14',
        ]);

        $response = $this->getJson(
            '/api/v1/medication-administrations?resident_id='.$resident->id.'&date_from=2026-06-14&date_to=2026-06-14'
        );

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($earlyJune14->id, $ids, 'Early-morning Pacific dose must be included in its own Pacific day.');
    }
}
