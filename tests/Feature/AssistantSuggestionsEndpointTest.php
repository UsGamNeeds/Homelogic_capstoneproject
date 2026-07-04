<?php

namespace Tests\Feature;

use App\Constants\Modules;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantSuggestionsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/assistant/suggestions')->assertUnauthorized();
    }

    public function test_returns_resident_based_suggestions(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/assistant/suggestions')->assertOk();
        $suggestions = $res->json('data.suggestions');

        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual(3, count($suggestions));
        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('label', $suggestions[0]);
        $this->assertArrayHasKey('message', $suggestions[0]);
    }

    public function test_does_not_leak_residents_from_another_facility(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);

        $other = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $other->id]);
        Resident::factory()->create(['branch_id' => $otherBranch->id, 'first_name' => 'Zebedee', 'last_name' => 'Cross']);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/assistant/suggestions')->assertOk();
        $body = json_encode($res->json('data.suggestions'));

        $this->assertStringNotContainsString('Zebedee', $body);
    }

    /**
     * Gate 1: requireModuleAccess(Modules::MEDICATIONS). Facility explicitly
     * disables the medications module (facility_modules.is_enabled = false),
     * so even a branch admin must be blocked before reaching the permission
     * check or the assistant service. Mirrors the deny-path arrange block in
     * tests/Feature/AssistantEndpointTest.php.
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

        $this->getJson('/api/v1/assistant/suggestions')->assertForbidden();
    }
}
