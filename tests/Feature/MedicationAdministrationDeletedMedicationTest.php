<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class MedicationAdministrationDeletedMedicationTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    private function makeMedicationWithAdministration(int $residentId, int $userId, string $name, string $status): array
    {
        $medication = Medication::create([
            'resident_id' => $residentId,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'instructions' => 'b.i.d',
            'time_1' => '00:00:00',
            'created_by' => $userId,
            'is_active' => true,
            'start_date' => '2020-01-01',
        ]);

        $administration = MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $residentId,
            'branch_id' => $this->branch->id,
            'administered_by' => $userId,
            'administered_at' => Carbon::parse('2026-06-15 00:00:00', config('app.timezone')),
            'status' => $status,
        ]);

        return [$medication, $administration];
    }

    public function test_index_excludes_administrations_for_soft_deleted_medications(): void
    {
        $user = $this->createAndActAs('administrator');
        $resident = $this->createResident($this->branch);

        [$keptMed, $keptAdmin] = $this->makeMedicationWithAdministration(
            $resident->id, $user->id, 'Kept Medication', 'completed'
        );
        [$deletedMed, $orphanAdmin] = $this->makeMedicationWithAdministration(
            $resident->id, $user->id, 'Deleted Medication', 'missed'
        );

        // Soft-delete the second medication (as the app does on destroy()).
        $deletedMed->delete();

        $response = $this->getJson('/api/v1/medication-administrations?resident_id='.$resident->id);
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($keptAdmin->id, $ids, 'Administration for an active medication must still appear.');
        $this->assertNotContains(
            $orphanAdmin->id,
            $ids,
            'Administration for a soft-deleted medication must NOT appear in the log history.'
        );
    }

    public function test_stats_excludes_missed_doses_of_soft_deleted_medications(): void
    {
        $user = $this->createAndActAs('administrator');
        $resident = $this->createResident($this->branch);

        $this->makeMedicationWithAdministration($resident->id, $user->id, 'Kept Medication', 'completed');
        [$deletedMed] = $this->makeMedicationWithAdministration($resident->id, $user->id, 'Deleted Medication', 'missed');
        $deletedMed->delete();

        $response = $this->getJson('/api/v1/medication-administrations/stats?resident_id='.$resident->id);
        $response->assertOk();

        $this->assertSame(0, (int) $response->json('missed'), 'Missed count must not include doses of deleted medications.');
        $this->assertSame(1, (int) $response->json('total'), 'Total must only count doses of existing medications.');
    }
}
