<?php

namespace Tests\Feature;

use App\Filament\Widgets\AdminSystemHealthWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * These widgets cache their figures. The models underneath are scoped
 * correctly, and on their own that is not enough: a cache key with no
 * facility in it means the first home to open the dashboard fills the key
 * and every home after it is served that home's numbers until the entry
 * expires. No amount of model scoping defends against a shared key, and a
 * suite that exercises one tenant at a time will never see it.
 *
 * Each test below loads a widget as one facility and then as another, in
 * that order, and asserts the second sees its own numbers.
 */
class DashboardWidgetTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Facility $small;

    private Facility $large;

    protected function setUp(): void
    {
        parent::setUp();

        // Two residents, one member of staff, one medication.
        $this->small = $this->facilityWith('Small Home', residents: 2, staff: 1, medications: 1);

        // Deliberately larger, so a leaked number is unmistakable.
        $this->large = $this->facilityWith('Large Home', residents: 7, staff: 4, medications: 5);
    }

    public function test_resident_count_is_not_served_from_another_facilitys_cache(): void
    {
        $this->actAs($this->small);
        $this->assertSame(2, $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount'));

        $this->actAs($this->large);
        $this->assertSame(
            7,
            $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount'),
            'Large Home was served Small Home\'s resident count out of a shared cache key.'
        );
    }

    public function test_medication_count_is_not_served_from_another_facilitys_cache(): void
    {
        $this->actAs($this->large);
        $this->assertSame(5, $this->invokePrivate(new StatsOverviewWidget, 'getMedicationCount'));

        $this->actAs($this->small);
        $this->assertSame(1, $this->invokePrivate(new StatsOverviewWidget, 'getMedicationCount'));
    }

    /**
     * Staff is the awkward one. User is permanently exempt from FacilityScope
     * (FacilityScope.php:39-41, to avoid recursion during auth), so this count
     * is global no matter what the cache key says.
     */
    public function test_staff_count_covers_only_the_viewers_facility(): void
    {
        $this->actAs($this->small);
        $this->assertSame(
            1,
            $this->invokePrivate(new StatsOverviewWidget, 'getStaffCount'),
            'Staff count is platform-wide: User has no FacilityScope, so tenant-keying the cache is not enough.'
        );

        $this->actAs($this->large);
        $this->assertSame(4, $this->invokePrivate(new StatsOverviewWidget, 'getStaffCount'));
    }

    public function test_system_health_is_not_served_from_another_facilitys_cache(): void
    {
        $this->actAs($this->small);
        $small = (new AdminSystemHealthWidget)->getViewData();
        $this->assertSame(1, $small['total_users']);

        $this->actAs($this->large);
        $large = (new AdminSystemHealthWidget)->getViewData();

        $this->assertSame(
            4,
            $large['total_users'],
            'Large Home was served Small Home\'s whole system-health payload from a shared cache key.'
        );
    }

    public function test_a_branch_admin_does_not_poison_the_facility_administrators_figures(): void
    {
        // FacilityScope narrows the 'admin' role to its own branch, so the two
        // viewers legitimately see different numbers within one facility.
        $branches = Branch::withoutGlobalScopes()->where('facility_id', $this->large->id)->get();
        $this->assertGreaterThan(1, $branches->count(), 'Fixture needs two branches to test this.');

        $branchAdmin = User::factory()->create([
            'facility_id' => $this->large->id,
            'assigned_branch_id' => $branches->first()->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($branchAdmin);
        app()->instance('facility', $this->large);

        $narrowed = $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount');
        $this->assertLessThan(7, $narrowed, 'A branch admin should see only their branch.');

        // The facility administrator must not now be handed the branch figure.
        $this->actAs($this->large);
        $this->assertSame(7, $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount'));
    }

    public function test_a_viewer_with_no_facility_does_not_share_a_bucket_with_a_real_one(): void
    {
        $stranded = User::factory()->create([
            'facility_id' => null,
            'assigned_branch_id' => null,
            'role' => 'administrator',
            'is_active' => true,
        ]);
        $this->actingAs($stranded);
        app()->forgetInstance('facility');

        // FacilityScope fails open for this viewer, so the figure is global.
        // Whatever it is, it must not become Small Home's answer.
        $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount');

        $this->actAs($this->small);
        $this->assertSame(2, $this->invokePrivate(new StatsOverviewWidget, 'getResidentCount'));
    }

    private function actAs(Facility $facility): User
    {
        $branch = Branch::withoutGlobalScopes()->where('facility_id', $facility->id)->firstOrFail();

        $user = User::withoutGlobalScopes()->where('facility_id', $facility->id)
            ->where('role', 'administrator')
            ->firstOrFail();

        $this->actingAs($user);
        app()->instance('facility', $facility);

        return $user;
    }

    private function invokePrivate(object $widget, string $method): int
    {
        $reflection = new ReflectionMethod($widget, $method);
        $reflection->setAccessible(true);

        return (int) $reflection->invoke($widget);
    }

    private function facilityWith(string $name, int $residents, int $staff, int $medications): Facility
    {
        $facility = Facility::factory()->create(['name' => $name]);

        $branchOne = Branch::factory()->create(['facility_id' => $facility->id]);
        $branchTwo = Branch::factory()->create(['facility_id' => $facility->id]);

        $admin = User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branchOne->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);

        // One administrator already counts as staff; add the rest.
        for ($i = 1; $i < $staff; $i++) {
            User::factory()->create([
                'facility_id' => $facility->id,
                'assigned_branch_id' => $branchOne->id,
                'role' => 'caregiver',
                'is_active' => true,
            ]);
        }

        $made = [];
        for ($i = 0; $i < $residents; $i++) {
            $made[] = Resident::withoutGlobalScopes()->create([
                'name' => $name.' Resident '.$i,
                'first_name' => 'Res', 'last_name' => 'Ident'.$i,
                // Spread across both branches so the branch-admin test has a
                // genuinely narrower number to find.
                'branch_id' => $i % 2 === 0 ? $branchOne->id : $branchTwo->id,
                'date_of_birth' => '1950-01-15', 'gender' => 'female',
                'admission_date' => '2024-01-01', 'is_active' => true, 'status' => 'active',
            ]);
        }

        for ($i = 0; $i < $medications; $i++) {
            Medication::withoutGlobalScopes()->create([
                'resident_id' => $made[$i % count($made)]->id,
                'branch_id' => $branchOne->id,
                'name' => 'Tablet '.$i,
                'instructions' => 'oral',
                'time_1' => '08:00:00',
                'created_by' => $admin->id,
                'is_active' => true,
                'start_date' => '2024-01-01',
            ]);
        }

        return $facility;
    }
}
