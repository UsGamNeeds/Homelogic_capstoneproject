<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

class AccountDeletionRequestTest extends TestCase
{
    use RefreshDatabase, SetupFacility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->createFacilityAndBranch();
    }

    public function test_authenticated_caregiver_can_submit_a_deletion_request(): void
    {
        User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $caregiver = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
            // Mirrors the real-world case: only `name` is populated, while the
            // optional first/last name columns are left empty.
            'name' => 'Fatou Ceesay',
            'first_name' => null,
            'last_name' => null,
        ]);

        Sanctum::actingAs($caregiver, ['*']);

        $response = $this->postJson('/api/v1/account-deletion-requests', [
            'reason' => 'No longer working at this facility.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'status', 'created_at']]);

        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $caregiver->id,
            'facility_id' => $this->facility->id,
            'status' => 'pending',
        ]);

        $this->assertEquals(1, Notification::where('type', 'account_deletion_request')->count());

        // The admin must be told who asked, and the link must reach a route the
        // SPA actually serves.
        $notification = Notification::where('type', 'account_deletion_request')->firstOrFail();
        $this->assertStringContainsString('Fatou Ceesay', $notification->message);
        $this->assertSame("/team/users/{$caregiver->id}", $notification->action_url);
    }

    public function test_second_request_while_pending_returns_409_and_does_not_duplicate(): void
    {
        User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $caregiver = User::factory()->create([
            'facility_id' => $this->facility->id,
            'assigned_branch_id' => $this->branch->id,
            'role' => 'caregiver',
            'is_active' => true,
        ]);

        Sanctum::actingAs($caregiver, ['*']);

        $this->postJson('/api/v1/account-deletion-requests', [
            'reason' => 'First request.',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/account-deletion-requests', [
            'reason' => 'Second request.',
        ]);

        $response->assertStatus(409);

        $this->assertEquals(1, AccountDeletionRequest::where('user_id', $caregiver->id)->count());
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/v1/account-deletion-requests', [
            'reason' => 'Should not work.',
        ]);

        $response->assertStatus(401);
    }
}
