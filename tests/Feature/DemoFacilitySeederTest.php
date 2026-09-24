<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\SleepRecord;
use App\Models\TLog;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\DemoFacilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFacilitySeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_BRANCHES = 2;

    private const EXPECTED_RESIDENTS_PER_BRANCH = 5;

    private const EXPECTED_RESIDENTS = self::EXPECTED_BRANCHES * self::EXPECTED_RESIDENTS_PER_BRANCH;

    private const EXPECTED_MEDICATIONS = 16;

    private const HISTORY_DAYS = 7;

    public function test_seeder_creates_facility_users_and_residents(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $facility = $this->demoFacility();
        $this->assertNotNull($facility);

        $admin = User::where('email', 'demo.admin@homelogic360.net')->first();
        $caregiver = User::where('email', 'demo.caregiver@homelogic360.net')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($caregiver);
        $this->assertTrue((bool) $admin->location_check_bypass);
        $this->assertTrue((bool) $caregiver->location_check_bypass);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertTrue((bool) $caregiver->is_active);

        $this->assertSame(self::EXPECTED_RESIDENTS, $this->demoResidentCount($facility));
    }

    public function test_seeder_creates_two_branches_with_five_residents_each(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $facility = $this->demoFacility();

        $branches = Branch::withoutGlobalScopes()
            ->where('facility_id', $facility->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(self::EXPECTED_BRANCHES, $branches);
        $this->assertSame(
            ['Demo Branch', 'Demo Branch Two'],
            $branches->pluck('name')->all()
        );

        foreach ($branches as $branch) {
            $this->assertSame(
                self::EXPECTED_RESIDENTS_PER_BRANCH,
                Resident::withoutGlobalScopes()->where('branch_id', $branch->id)->count(),
                "Branch {$branch->name} should hold ".self::EXPECTED_RESIDENTS_PER_BRANCH.' residents'
            );

            // Every branch needs coordinates or LocationService rejects clock-in
            // outright, bypass flag or not.
            $this->assertNotNull($branch->latitude);
            $this->assertNotNull($branch->longitude);
        }
    }

    public function test_each_branch_has_its_own_caregiver(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $facility = $this->demoFacility();
        $branches = Branch::withoutGlobalScopes()
            ->where('facility_id', $facility->id)
            ->orderBy('id')
            ->get();

        $caregiverOne = User::where('email', 'demo.caregiver@homelogic360.net')->firstOrFail();
        $caregiverTwo = User::where('email', 'demo.caregiver2@homelogic360.net')->firstOrFail();

        $this->assertSame($branches[0]->id, $caregiverOne->assigned_branch_id);
        $this->assertSame($branches[1]->id, $caregiverTwo->assigned_branch_id);

        $this->assertTrue((bool) $caregiverTwo->location_check_bypass);
        $this->assertTrue((bool) $caregiverTwo->is_active);
        $this->assertTrue($caregiverTwo->hasRole('caregiver'));

        // The point of a second caregiver is that branch scoping is testable:
        // neither caregiver's branch may contain the other's residents.
        $this->assertNotSame($caregiverOne->assigned_branch_id, $caregiverTwo->assigned_branch_id);
    }

    public function test_medications_land_on_the_resident_they_were_defined_for(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        // An aggregate medication count would still pass if the seeder attached
        // the right drugs to the wrong residents, so pin a few by name.
        $expected = [
            'Marigold' => ['Atorvastatin', 'Lisinopril'],
            'Bartholomew' => ['Metformin'],
            'Ottoline' => ['Donepezil'],
            'Mortimer' => ['Levothyroxine', 'Metformin'],
        ];

        foreach ($expected as $firstName => $drugNames) {
            $resident = Resident::withoutGlobalScopes()
                ->where('first_name', $firstName)
                ->firstOrFail();

            $actual = Medication::withoutGlobalScopes()
                ->where('resident_id', $resident->id)
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            $this->assertSame($drugNames, $actual, "{$firstName} has the wrong medications");
        }
    }

    public function test_records_are_attributed_to_the_caregiver_of_their_own_branch(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $caregiverByBranch = User::whereIn('email', [
            'demo.caregiver@homelogic360.net',
            'demo.caregiver2@homelogic360.net',
        ])->get()->keyBy('assigned_branch_id');

        $this->assertCount(2, $caregiverByBranch);

        foreach ($this->demoBranchIds() as $branchId) {
            $expectedCaregiverId = $caregiverByBranch[$branchId]->id;

            $this->assertSame(
                [$expectedCaregiverId],
                MedicationAdministration::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->pluck('administered_by')
                    ->unique()
                    ->values()
                    ->all()
            );

            $this->assertSame(
                [$expectedCaregiverId],
                VitalSign::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->pluck('taken_by')
                    ->unique()
                    ->values()
                    ->all()
            );

            $this->assertSame(
                [$expectedCaregiverId],
                SleepRecord::withoutGlobalScopes()
                    ->where('branch_id', $branchId)
                    ->pluck('created_by')
                    ->unique()
                    ->values()
                    ->all()
            );
        }
    }

    public function test_seeder_records_a_week_of_medication_history_but_leaves_today_open(): void
    {
        // The seeder and these assertions both read the clock. Freeze it so a
        // run straddling midnight cannot flake.
        $this->freezeTime();

        $this->seed(DemoFacilitySeeder::class);

        $branchIds = $this->demoBranchIds();

        $administrations = MedicationAdministration::withoutGlobalScopes()
            ->whereIn('branch_id', $branchIds)
            ->get();

        $this->assertGreaterThan(0, $administrations->count());

        // Today must stay empty: MedicationController's `for_administration`
        // path treats a medication as done once today holds a completed or
        // refused row, so seeding today would leave nothing to administer.
        $today = now()->toDateString();
        $this->assertCount(
            0,
            $administrations->filter(fn ($a) => $a->administered_at->toDateString() === $today),
            'Seeder must not write administrations dated today'
        );

        $distinctDays = $administrations
            ->map(fn ($a) => $a->administered_at->toDateString())
            ->unique();

        $this->assertCount(self::HISTORY_DAYS, $distinctDays);

        $oldest = now()->subDays(self::HISTORY_DAYS)->toDateString();
        $this->assertSame($oldest, $distinctDays->sort()->first());
        $this->assertSame(now()->subDay()->toDateString(), $distinctDays->sort()->last());

        // Mixed statuses are what make missed-dose counts and status filters
        // worth testing at all.
        $statuses = $administrations->pluck('status')->unique();
        $this->assertContains('completed', $statuses);
        $this->assertContains('missed', $statuses);
        $this->assertContains('refused', $statuses);

        // A dose that was not given carries no dosage, but still records who
        // logged it, because administered_by is NOT NULL.
        $notGiven = $administrations->firstWhere('status', 'missed');
        $this->assertNull($notGiven->dosage_given);
        $this->assertNotNull($notGiven->administered_by);
    }

    public function test_seeder_records_a_week_of_vitals_and_sleep(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $branchIds = $this->demoBranchIds();

        $this->assertSame(
            self::EXPECTED_RESIDENTS * self::HISTORY_DAYS,
            VitalSign::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->count()
        );

        $this->assertSame(
            self::EXPECTED_RESIDENTS * self::HISTORY_DAYS,
            SleepRecord::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->count()
        );
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $branchIds = $this->demoBranchIds();

        // Every model the seeder writes daily or per-resident rows into. Each
        // one relies on its own firstOrCreate key holding steady; if any key
        // ever drifts, the second run silently doubles that table.
        $dailyModels = [
            MedicationAdministration::class,
            VitalSign::class,
            SleepRecord::class,
            Appointment::class,
            TLog::class,
        ];

        $countsAfterFirstRun = [];
        foreach ($dailyModels as $model) {
            $countsAfterFirstRun[$model] = $model::withoutGlobalScopes()
                ->whereIn('branch_id', $branchIds)
                ->count();
            $this->assertGreaterThan(0, $countsAfterFirstRun[$model], "{$model} seeded nothing");
        }

        $administrationsAfterFirstRun = $countsAfterFirstRun[MedicationAdministration::class];

        $this->seed(DemoFacilitySeeder::class);

        foreach ($dailyModels as $model) {
            $this->assertSame(
                $countsAfterFirstRun[$model],
                $model::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->count(),
                "Re-running the seeder duplicated rows in {$model}"
            );
        }

        $this->assertSame(
            1,
            Facility::withoutGlobalScopes()->where('name', 'HomeLogic360 Demo Facility')->count()
        );

        $facility = $this->demoFacility();

        $this->assertCount(
            self::EXPECTED_BRANCHES,
            Branch::withoutGlobalScopes()->where('facility_id', $facility->id)->get()
        );

        $userCount = User::whereIn('email', [
            'demo.admin@homelogic360.net',
            'demo.caregiver@homelogic360.net',
            'demo.caregiver2@homelogic360.net',
        ])->count();

        $this->assertSame(3, $userCount);

        $this->assertSame(self::EXPECTED_RESIDENTS, $this->demoResidentCount($facility));

        $this->assertSame(
            self::EXPECTED_MEDICATIONS,
            Medication::withoutGlobalScopes()
                ->whereHas('branch', fn ($q) => $q->where('facility_id', $facility->id))
                ->count()
        );

        $this->assertSame(
            $administrationsAfterFirstRun,
            MedicationAdministration::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->count(),
            'Re-running the seeder must not duplicate administration rows'
        );
    }

    public function test_seeder_never_touches_facility_id_one(): void
    {
        // A migration already seeds a real facility at id 1, exactly as production
        // does, so do not create one here. Capture its state, run the seeder, and
        // prove the seeder left it completely alone.
        $before = Facility::withoutGlobalScopes()->findOrFail(1);
        $beforeName = $before->name;
        $beforeResidents = Resident::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->where('facility_id', 1))
            ->count();
        $beforeUsers = User::withoutGlobalScopes()->where('facility_id', 1)->count();

        $this->seed(DemoFacilitySeeder::class);

        $after = Facility::withoutGlobalScopes()->findOrFail(1);
        $this->assertSame($beforeName, $after->name);
        $this->assertSame(
            $beforeResidents,
            Resident::withoutGlobalScopes()
                ->whereHas('branch', fn ($q) => $q->where('facility_id', 1))
                ->count()
        );
        $this->assertSame(
            $beforeUsers,
            User::withoutGlobalScopes()->where('facility_id', 1)->count()
        );

        $demoFacility = $this->demoFacility();

        $this->assertNotNull($demoFacility);
        $this->assertNotSame(1, $demoFacility->id);
    }

    private function demoFacility(): ?Facility
    {
        return Facility::withoutGlobalScopes()
            ->where('name', 'HomeLogic360 Demo Facility')
            ->first();
    }

    /**
     * @return array<int, int>
     */
    private function demoBranchIds(): array
    {
        return Branch::withoutGlobalScopes()
            ->where('facility_id', $this->demoFacility()->id)
            ->pluck('id')
            ->all();
    }

    private function demoResidentCount(Facility $facility): int
    {
        return Resident::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->where('facility_id', $facility->id))
            ->count();
    }
}
