<?php

namespace Tests\Feature;

use App\Constants\Modules;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/assistant/query', ['message' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_returns_answer_for_authorized_user(): void
    {
        Http::fake();
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', ['message' => 'meds for Mary Smith today'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'todays_meds');
    }

    public function test_validates_message_is_required(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', [])
            ->assertStatus(422);
    }

    /**
     * Gate 1: requireModuleAccess(Modules::MEDICATIONS). Facility explicitly
     * disables the medications module (facility_modules.is_enabled = false),
     * so even a branch admin must be blocked before reaching the permission
     * check or the assistant service.
     */
    public function test_denies_when_medications_module_disabled(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $facility->disableModule(Modules::MEDICATIONS);

        $user = User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', ['message' => 'meds for Mary Smith today'])
            ->assertStatus(403);
    }

    /**
     * Gate 2: requirePermission('view_medications'). Facility has the
     * medications module enabled (default-open), but the user is a
     * non-admin role (caregiver) with no permission rows seeded in the
     * test database, so hasPermission() resolves to false and the
     * request must be blocked even though the module gate passed.
     */
    public function test_denies_when_user_lacks_view_medications_permission(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);

        $user = User::factory()->create([
            'facility_id' => $facility->id,
            'assigned_branch_id' => $branch->id,
            'role' => 'caregiver',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', ['message' => 'meds for Mary Smith today'])
            ->assertStatus(403);
    }

    public function test_forwards_context_resident_id(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $facility = \App\Models\Facility::factory()->create();
        $branch = \App\Models\Branch::factory()->create(['facility_id' => $facility->id]);
        $user = \App\Models\User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        $resident = \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith',
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        // message names nobody; context id resolves the resident
        $this->postJson('/api/v1/assistant/query', [
            'message' => 'all his meds',
            'context_resident_id' => $resident->id,
        ])->assertOk()->assertJsonPath('data.resident.id', $resident->id);
    }

    public function test_rejects_non_integer_context_id(): void
    {
        $facility = \App\Models\Facility::factory()->create();
        $user = \App\Models\User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', [
            'message' => 'hi',
            'context_resident_id' => 'not-an-int',
        ])->assertStatus(422);
    }
}
