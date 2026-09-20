<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Medication;
use App\Models\Resident;
use App\Models\User;
use Database\Seeders\DemoFacilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFacilitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_facility_users_and_residents(): void
    {
        $this->seed(DemoFacilitySeeder::class);

        $facility = Facility::withoutGlobalScopes()
            ->where('name', 'HomeLogic360 Demo Facility')
            ->first();

        $this->assertNotNull($facility);

        $admin = User::where('email', 'demo.admin@homelogic360.net')->first();
        $caregiver = User::where('email', 'demo.caregiver@homelogic360.net')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($caregiver);
        $this->assertTrue((bool) $admin->location_check_bypass);
        $this->assertTrue((bool) $caregiver->location_check_bypass);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertTrue((bool) $caregiver->is_active);

        $residentCount = Resident::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->where('facility_id', $facility->id))
            ->count();

        $this->assertSame(3, $residentCount);
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(DemoFacilitySeeder::class);
        $this->seed(DemoFacilitySeeder::class);

        $facilityCount = Facility::withoutGlobalScopes()
            ->where('name', 'HomeLogic360 Demo Facility')
            ->count();

        $this->assertSame(1, $facilityCount);

        $userCount = User::whereIn('email', [
            'demo.admin@homelogic360.net',
            'demo.caregiver@homelogic360.net',
        ])->count();

        $this->assertSame(2, $userCount);

        $facility = Facility::withoutGlobalScopes()
            ->where('name', 'HomeLogic360 Demo Facility')
            ->first();

        $residentCount = Resident::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->where('facility_id', $facility->id))
            ->count();

        $this->assertSame(3, $residentCount);

        $medicationCount = Medication::withoutGlobalScopes()
            ->whereHas('branch', fn ($q) => $q->where('facility_id', $facility->id))
            ->count();

        $this->assertSame(3, $medicationCount);
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

        $demoFacility = Facility::withoutGlobalScopes()
            ->where('name', 'HomeLogic360 Demo Facility')
            ->first();

        $this->assertNotNull($demoFacility);
        $this->assertNotSame(1, $demoFacility->id);
    }
}
