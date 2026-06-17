<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class BranchCapacityTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    public function test_administrator_can_set_branch_resident_capacity_on_create(): void
    {
        $admin = $this->createAndActAs('administrator');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/branches', [
            'name' => 'North Wing',
            'facility_id' => $this->facility->id,
            'resident_capacity' => 8,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('resident_capacity', 8);

        $this->assertDatabaseHas('branches', [
            'name' => 'North Wing',
            'resident_capacity' => 8,
        ]);
    }

    public function test_administrator_can_update_and_clear_branch_resident_capacity(): void
    {
        $admin = $this->createAndActAs('administrator');

        $updateResponse = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/branches/{$this->branch->id}", [
            'resident_capacity' => 12,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('resident_capacity', 12);

        $clearResponse = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/branches/{$this->branch->id}", [
            'resident_capacity' => null,
        ]);

        $clearResponse->assertOk();
        $clearResponse->assertJsonPath('resident_capacity', null);

        $this->assertNull(Branch::withoutGlobalScopes()->find($this->branch->id)->resident_capacity);
    }

    public function test_branch_resident_capacity_must_be_within_allowed_range(): void
    {
        $admin = $this->createAndActAs('administrator');

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/branches/{$this->branch->id}", [
            'resident_capacity' => 10000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resident_capacity']);
    }
}
