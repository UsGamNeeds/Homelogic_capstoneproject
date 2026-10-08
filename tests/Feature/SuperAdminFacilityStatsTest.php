<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The super-admin facility detail page renders "Total Branches" and "Total
 * Users" from `branches_count` and `users_count` on the facility payload
 * (resources/js/pages/FacilityView.jsx). FacilityController::show() returned
 * the bare model, so both keys were absent, the frontend's `|| 0` fallback
 * took over, and every facility reported zero however many branches and users
 * it actually had.
 */
class SuperAdminFacilityStatsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $superAdmin = User::factory()->superAdmin()->create(['facility_id' => null]);
        Sanctum::actingAs($superAdmin, ['*']);

        return $superAdmin;
    }

    public function test_show_returns_branch_and_user_counts_for_a_super_admin(): void
    {
        $this->superAdmin();

        $facility = Facility::factory()->create();
        Branch::factory()->count(2)->create(['facility_id' => $facility->id]);
        User::factory()->count(3)->create(['facility_id' => $facility->id]);

        $response = $this->getJson("/api/v1/facilities/{$facility->id}");

        $response->assertOk();
        $response->assertJsonPath('branches_count', 2);
        $response->assertJsonPath('users_count', 3);
    }

    public function test_counts_describe_the_facility_being_viewed_not_the_viewer(): void
    {
        // Guards against a "fix" that counts through a tenancy-scoped query
        // bound to the viewer's own facility rather than the one in the URL.
        $this->superAdmin();

        $viewed = Facility::factory()->create();
        Branch::factory()->count(2)->create(['facility_id' => $viewed->id]);
        User::factory()->count(3)->create(['facility_id' => $viewed->id]);

        $other = Facility::factory()->create();
        Branch::factory()->count(5)->create(['facility_id' => $other->id]);
        User::factory()->count(7)->create(['facility_id' => $other->id]);

        $response = $this->getJson("/api/v1/facilities/{$viewed->id}");

        $response->assertOk();
        $response->assertJsonPath('branches_count', 2);
        $response->assertJsonPath('users_count', 3);
    }

    public function test_a_facility_with_nothing_attached_reports_zero(): void
    {
        $this->superAdmin();

        $facility = Facility::factory()->create();

        $response = $this->getJson("/api/v1/facilities/{$facility->id}");

        $response->assertOk();
        $response->assertJsonPath('branches_count', 0);
        $response->assertJsonPath('users_count', 0);
    }

    public function test_an_administrator_sees_the_counts_for_their_own_facility(): void
    {
        // The page is super-admin-facing, but show() is reachable by a facility
        // administrator for their own facility. Branch carries FacilityScope,
        // so a scoped count must still resolve to that same facility.
        $facility = Facility::factory()->create();
        Branch::factory()->count(2)->create(['facility_id' => $facility->id]);

        $administrator = User::factory()->create([
            'facility_id' => $facility->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);
        Sanctum::actingAs($administrator, ['*']);
        app()->instance('facility', $facility);

        $response = $this->getJson("/api/v1/facilities/{$facility->id}");

        $response->assertOk();
        $response->assertJsonPath('branches_count', 2);

        // The administrator themselves is one of the facility's users.
        $response->assertJsonPath(
            'users_count',
            User::where('facility_id', $facility->id)->count()
        );
    }
}
