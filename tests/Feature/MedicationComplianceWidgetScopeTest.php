<?php

namespace Tests\Feature;

use App\Filament\Widgets\MedicationComplianceWidget;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The compliance widget counts medication administrations directly, and
 * MedicationAdministration carries no FacilityScope of its own — its only
 * global scope is `medicationExists`, a raw subquery against the medications
 * table that never reaches the Eloquent scope. Every other caller in the
 * codebase arrives through medication ids that were already branch-filtered.
 * This widget queries the table, so it has to do its own filtering.
 */
class MedicationComplianceWidgetScopeTest extends TestCase
{
    use RefreshDatabase;

    private Facility $ours;

    private Facility $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00', config('app.timezone')));

        $this->ours = Facility::factory()->create(['name' => 'Our Home']);
        $this->theirs = Facility::factory()->create(['name' => 'Their Home']);

        // Ours: two completed, one missed.
        $this->administrations($this->ours, ['completed', 'completed', 'missed']);

        // Theirs: a much larger, much worse month that we must not see.
        $this->administrations($this->theirs, array_merge(
            array_fill(0, 10, 'completed'),
            array_fill(0, 30, 'missed'),
        ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_administrator_sees_only_their_own_facility(): void
    {
        $this->actingAs($this->administratorFor($this->ours));

        $data = (new MedicationComplianceWidget)->getViewData();

        $this->assertSame(3, $data['total_scheduled'], 'Counted another facility\'s administrations.');
        $this->assertSame(2, $data['completed']);
        $this->assertSame(1, $data['missed']);
        $this->assertSame(66.7, $data['compliance_rate']);
    }

    public function test_the_other_facility_sees_its_own_numbers(): void
    {
        $this->actingAs($this->administratorFor($this->theirs));

        $data = (new MedicationComplianceWidget)->getViewData();

        $this->assertSame(40, $data['total_scheduled']);
        $this->assertSame(10, $data['completed']);
        $this->assertSame(30, $data['missed']);
    }

    public function test_a_super_admin_still_sees_everything(): void
    {
        $user = User::factory()->create([
            'facility_id' => null,
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $data = (new MedicationComplianceWidget)->getViewData();

        $this->assertSame(43, $data['total_scheduled']);
        $this->assertSame(12, $data['completed']);
    }

    public function test_a_user_with_no_facility_is_shown_nothing_rather_than_everything(): void
    {
        $user = User::factory()->create([
            'facility_id' => null,
            'role' => 'administrator',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $data = (new MedicationComplianceWidget)->getViewData();

        $this->assertSame(0, $data['total_scheduled']);
        $this->assertSame(0, $data['compliance_rate']);
    }

    private function administratorFor(Facility $facility): User
    {
        $branch = Branch::where('facility_id', $facility->id)->firstOrFail();

        return User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function administrations(Facility $facility, array $statuses): void
    {
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);

        $resident = Resident::withoutGlobalScopes()->create([
            'name' => 'Resident '.$facility->id,
            'first_name' => 'Res', 'last_name' => 'Ident',
            'branch_id' => $branch->id,
            'date_of_birth' => '1950-01-15', 'gender' => 'female',
            'admission_date' => '2024-01-01', 'is_active' => true, 'status' => 'active',
        ]);

        $creator = User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $medication = Medication::withoutGlobalScopes()->create([
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'name' => 'Tablet',
            'instructions' => 'oral',
            'time_1' => '08:00:00',
            'created_by' => $creator->id,
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        foreach ($statuses as $i => $status) {
            MedicationAdministration::create([
                'medication_id' => $medication->id,
                'resident_id' => $resident->id,
                'branch_id' => $branch->id,
                'administered_by' => $creator->id,
                'administered_at' => Carbon::now()->subDays(1)->subMinutes($i),
                'status' => $status,
            ]);
        }
    }
}
