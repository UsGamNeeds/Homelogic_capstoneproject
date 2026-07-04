<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\ResidentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentResolverTest extends TestCase
{
    use RefreshDatabase;

    private function seedFacilityUser(): array
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create([
            'facility_id' => $facility->id,
            'role' => 'admin',
        ]);

        return [$facility, $branch, $user];
    }

    public function test_matches_single_resident_and_redacts_name(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Resident::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('what meds is Mary Smith on today', $user);

        $this->assertSame('one', $resolution->status);
        $this->assertCount(1, $resolution->matches);
        $this->assertStringContainsString('{RESIDENT}', $resolution->redacted);
        $this->assertStringNotContainsStringIgnoringCase('Mary', $resolution->redacted);
    }

    public function test_no_match_returns_none_status(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('what meds are due', $user);

        $this->assertSame('none', $resolution->status);
        $this->assertCount(0, $resolution->matches);
    }

    public function test_two_distinct_residents_is_ambiguous(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor']);
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('meds for Mary', $user);

        $this->assertSame('ambiguous', $resolution->status);
        $this->assertCount(2, $resolution->matches);
    }

    public function test_does_not_match_resident_from_another_facility(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();

        $otherFacility = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $otherFacility->id]);
        Resident::factory()->create(['branch_id' => $otherBranch->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);

        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('meds for Bob Jones today', $user);

        $this->assertSame('none', $resolution->status);
    }

    public function test_redacts_all_occurrences_and_forms_of_same_resident_name(): void
    {
        [, $branch, $user] = $this->seedFacilityUser();
        \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $resolution = app(\App\Services\ResidentResolver::class)
            ->resolve('Mary Smith needs meds. What time should Mary take them?', $user);

        $this->assertSame('one', $resolution->status);
        $this->assertStringNotContainsStringIgnoringCase('Mary', $resolution->redacted);
        $this->assertStringNotContainsStringIgnoringCase('Smith', $resolution->redacted);
    }
}
