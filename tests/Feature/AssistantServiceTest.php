<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixtures(): array
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        $resident = Resident::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
        Sanctum::actingAs($user);

        return [$facility, $branch, $user, $resident];
    }

    private function makeMedication(Resident $resident, Branch $branch, User $user, array $overrides = []): Medication
    {
        $drug = Drug::create([
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);

        return Medication::create(array_merge([
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'drug_id' => $drug->id,
            'name' => 'Paracetamol 500mg',
            'instructions' => 't.i.d',
            'time_1' => '08:00',
            'is_active' => true,
            'start_date' => now()->subDay()->toDateString(),
            'created_by' => $user->id,
        ], $overrides));
    }

    public function test_todays_meds_lists_active_medication(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, $user);

        $result = app(AssistantService::class)->handle('what meds is Mary Smith on today', $user);

        $this->assertSame('todays_meds', $result['intent']);
        $this->assertStringContainsString('Paracetamol', $result['answer']);
        Http::assertNothingSent(); // fast-path handled it, no PHI to LLM
    }

    public function test_prn_reports_last_given(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $prn = $this->makeMedication($resident, $branch, $user, [
            'name' => 'Diazepam',
            'instructions' => 'PRN',
            'time_1' => null,
        ]);
        MedicationAdministration::create([
            'medication_id' => $prn->id,
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'administered_by' => $user->id,
            'administered_at' => now()->subHours(2),
            'status' => 'completed',
        ]);

        $result = app(AssistantService::class)->handle('any prn for Mary Smith', $user);

        $this->assertSame('prn_status', $result['intent']);
        $this->assertStringContainsString('Diazepam', $result['answer']);
        $this->assertStringContainsString('last given', $result['answer']);
    }

    public function test_unknown_resident_prompts_for_name_without_calling_llm(): void
    {
        Http::fake();
        [, , $user] = $this->seedFixtures();

        $result = app(AssistantService::class)->handle('what meds are due today', $user);

        $this->assertStringContainsStringIgnoringCase('which resident', $result['answer']);
        Http::assertNothingSent();
    }

    public function test_ambiguous_resident_asks_which_one(): void
    {
        Http::fake();
        [, $branch, $user] = $this->seedFixtures();
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor']);

        $result = app(AssistantService::class)->handle('meds for Mary', $user);

        $this->assertStringContainsStringIgnoringCase('which', $result['answer']);
    }
}
