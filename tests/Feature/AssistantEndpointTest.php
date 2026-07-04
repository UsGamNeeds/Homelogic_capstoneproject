<?php

namespace Tests\Feature;

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
}
