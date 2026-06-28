<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFacilityAndBranch();
    }

    /**
     * Before the fix, any authenticated user could POST a behaviour chart.
     * A caregiver with no granted behaviour permission must now be blocked.
     */
    public function test_caregiver_without_permission_cannot_create_behavior_chart(): void
    {
        $resident = $this->createResident();
        $caregiver = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);
        Sanctum::actingAs($caregiver, ['*']);
        app()->instance('facility', $this->facility);

        $response = $this->postJson('/api/v1/resident-charts', [
            'resident_id' => $resident->id,
            'chart_date' => '2026-04-15',
            'status' => 'submitted',
            'items' => [],
        ]);

        $response->assertForbidden();
    }

    /**
     * Admins bypass permission checks by design, so the same write succeeds
     * for an administrator (proves the gate did not break legitimate access).
     */
    public function test_administrator_can_reach_behavior_chart_create(): void
    {
        $this->createResident();
        $admin = $this->createAndActAs('administrator');

        $response = $this->postJson('/api/v1/resident-charts', [
            'resident_id' => 999999, // invalid on purpose
            'chart_date' => '2026-04-15',
            'status' => 'submitted',
            'items' => [],
        ]);

        // Passes the permission/module gates and reaches validation (422),
        // i.e. NOT blocked by a 403.
        $response->assertStatus(422);
    }

    public function test_caregiver_without_permission_cannot_bulk_edit_behavior_definitions(): void
    {
        $caregiver = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);
        Sanctum::actingAs($caregiver, ['*']);
        app()->instance('facility', $this->facility);

        $this->postJson('/api/v1/chart-data-definitions/bulk', ['data' => []])
            ->assertForbidden();
    }

    public function test_caregiver_without_permission_cannot_create_employee_document(): void
    {
        $caregiver = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);
        Sanctum::actingAs($caregiver, ['*']);
        app()->instance('facility', $this->facility);

        $this->postJson('/api/v1/employee-documents', [])
            ->assertForbidden();
    }
}
