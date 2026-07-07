<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityRegistration;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * Security Batch 1 — access-control hardening.
 *
 * Covers three exploitable-today gaps surfaced by the security audit:
 *   1. approveRegistration had no authorization (any user could mint a facility + admin).
 *   2. role was mass-assignable with no privilege gate (staff -> super_admin escalation).
 *   3. VisitorController::checkOut was a cross-tenant IDOR.
 */
class Batch1AccessControlTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    private function validUserPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser'.uniqid().'@example.com',
            'password' => 'password123',
            'phone_number' => '+12065550100',
            'date_of_birth' => '1990-01-01',
            'sex' => 'male',
            'date_employed' => '2024-01-01',
            'role' => 'caregiver',
        ], $overrides);
    }

    // --- 1. approveRegistration authorization ---

    public function test_non_super_admin_cannot_approve_facility_registration(): void
    {
        [$facility, $branch] = $this->createFacilityAndBranch();
        $this->createAndActAs('administrator', $facility, $branch);

        $registration = FacilityRegistration::create([
            'facility_name' => 'Sneaky Home',
            'contact_name' => 'Mallory',
            'email' => 'mallory@example.com',
            'requested_subdomain' => 'sneaky',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/facilities/approve-registration/{$registration->id}", []);

        $response->assertStatus(403);
    }

    public function test_super_admin_passes_approve_registration_guard(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['facility_id' => null]);
        Sanctum::actingAs($superAdmin, ['*']);

        $registration = FacilityRegistration::create([
            'facility_name' => 'Legit Home',
            'contact_name' => 'Owner',
            'email' => 'owner@example.com',
            'requested_subdomain' => 'legit',
            'status' => 'pending',
        ]);

        // Empty body -> guard passes, validation fails (422). Proves the
        // authorization guard let the super admin through.
        $response = $this->postJson("/api/v1/facilities/approve-registration/{$registration->id}", []);

        $response->assertStatus(422);
    }

    // --- 2. role-assignment privilege gate ---

    public function test_facility_admin_cannot_create_super_admin_user(): void
    {
        [$facility, $branch] = $this->createFacilityAndBranch();
        $this->createAndActAs('administrator', $facility, $branch);

        $response = $this->postJson('/api/v1/users', $this->validUserPayload([
            'role' => 'super_admin',
            'assigned_branch_id' => $branch->id,
        ]));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['role' => 'super_admin', 'facility_id' => $facility->id]);
    }

    public function test_facility_admin_cannot_promote_existing_user_to_super_admin(): void
    {
        [$facility, $branch] = $this->createFacilityAndBranch();
        $this->createAndActAs('administrator', $facility, $branch);

        $target = User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/users/{$target->id}", [
            'role' => 'super_admin',
        ]);

        $response->assertStatus(403);
        $this->assertSame('caregiver', $target->fresh()->role);
    }

    public function test_facility_admin_can_still_create_caregiver(): void
    {
        [$facility, $branch] = $this->createFacilityAndBranch();
        $this->createAndActAs('administrator', $facility, $branch);

        $response = $this->postJson('/api/v1/users', $this->validUserPayload([
            'role' => 'caregiver',
            'assigned_branch_id' => $branch->id,
        ]));

        $response->assertStatus(201);
    }

    // --- 3. Visitor checkOut cross-tenant IDOR ---

    public function test_user_cannot_check_out_visitor_from_another_facility(): void
    {
        [$facilityA, $branchA] = $this->createFacilityAndBranch('Facility A');
        [$facilityB, $branchB] = $this->createFacilityAndBranch('Facility B');

        // Staff member who checked the visitor in (belongs to facility B).
        $checker = User::factory()->create([
            'facility_id' => $facilityB->id,
            'assigned_branch_id' => $branchB->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);

        // Act as a user in facility A.
        $this->createAndActAs('administrator', $facilityA, $branchA);

        // Visitor belongs to facility B.
        $visitor = Visitor::create([
            'branch_id' => $branchB->id,
            'facility_id' => $facilityB->id,
            'first_name' => 'Bob',
            'last_name' => 'Guest',
            'visit_purpose' => 'visit',
            'check_in_at' => now(),
            'is_active' => true,
            'checked_in_by' => $checker->id,
        ]);

        $response = $this->postJson("/api/v1/visitors/{$visitor->id}/check-out", []);

        $response->assertStatus(404);
        $this->assertTrue((bool) $visitor->fresh()->is_active, 'Visitor must not be checked out cross-tenant');
    }
}
