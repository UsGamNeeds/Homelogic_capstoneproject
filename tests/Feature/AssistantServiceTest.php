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
        $this->assertStringContainsString("can't advise", $result['answer']);
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

    public function test_unclear_intent_returns_meds_and_prn_chips(): void
    {
        Http::fake(); // faked LLM returns empty -> parse() => 'unknown' -> chips
        [, $branch, $user, $resident] = $this->seedFixtures();

        $result = app(AssistantService::class)->handle('mary smith', $user);

        $this->assertSame('unknown', $result['intent']);
        $this->assertCount(3, $result['suggestions']);
        $labels = array_column($result['suggestions'], 'label');
        $this->assertContains("Today's meds", $labels);
        $this->assertContains('All meds', $labels);
        $this->assertContains('PRN', $labels);

        // Each chip message must round-trip to the right intent via the pipeline.
        $byLabel = collect($result['suggestions'])->keyBy('label');
        $meds = app(AssistantService::class)->handle($byLabel["Today's meds"]['message'], $user);
        $this->assertSame('todays_meds', $meds['intent']);
        $all = app(AssistantService::class)->handle($byLabel['All meds']['message'], $user);
        $this->assertSame('all_medications', $all['intent']);
        $prn = app(AssistantService::class)->handle($byLabel['PRN']['message'], $user);
        $this->assertSame('prn_status', $prn['intent']);
    }

    public function test_ambiguous_returns_one_chip_per_resident(): void
    {
        Http::fake();
        [, $branch, $user] = $this->seedFixtures();
        \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor',
        ]);

        $result = app(AssistantService::class)->handle('meds for Mary', $user);

        // Two residents named Mary -> ambiguous -> one chip each.
        $this->assertCount(2, $result['suggestions']);
        foreach ($result['suggestions'] as $s) {
            $this->assertStringContainsString("today's meds for", strtolower($s['message']));
        }
    }

    public function test_normal_answer_has_empty_suggestions(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, $user);

        $result = app(AssistantService::class)->handle('what meds is Mary Smith on today', $user);

        $this->assertSame('todays_meds', $result['intent']);
        $this->assertSame([], $result['suggestions']);
    }

    public function test_starter_suggestions_builds_resident_prompts(): void
    {
        [, $branch, $user] = $this->seedFixtures(); // seedFixtures creates resident "Mary Smith"
        \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Anna', 'last_name' => 'Bell',
        ]);

        $chips = app(AssistantService::class)->starterSuggestions($user, 3);

        $this->assertNotEmpty($chips);
        $this->assertLessThanOrEqual(3, count($chips));
        foreach ($chips as $c) {
            $this->assertArrayHasKey('label', $c);
            $this->assertArrayHasKey('message', $c);
            $this->assertMatchesRegularExpression("/^(today's meds for|PRN for) /i", $c['message']);
        }
    }

    public function test_uses_context_resident_when_message_has_no_name(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, $user, ['name' => 'Aspirin 75mg', 'instructions' => 'o.d']);

        // message names nobody; context id supplies the resident
        $result = app(AssistantService::class)->handle('show all his meds', $user, $resident->id);

        $this->assertSame('all_medications', $result['intent']);
        $this->assertSame($resident->id, $result['resident']['id']);
        $this->assertStringContainsString('Aspirin', $result['answer']);
        Http::assertNothingSent();
    }

    public function test_named_resident_overrides_context(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures(); // Mary Smith
        $other = Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Nora', 'last_name' => 'Quinn',
        ]);

        // context says $other, but message names Mary Smith -> Mary wins
        $result = app(AssistantService::class)->handle("today's meds for Mary Smith", $user, $other->id);

        $this->assertSame($resident->id, $result['resident']['id']);
    }

    public function test_foreign_context_id_is_ignored(): void
    {
        Http::fake();
        [, , $user] = $this->seedFixtures();
        $otherFacility = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $otherFacility->id]);
        $foreign = Resident::factory()->create([
            'branch_id' => $otherBranch->id, 'first_name' => 'Zed', 'last_name' => 'Cross',
        ]);

        $result = app(AssistantService::class)->handle('all his meds', $user, $foreign->id);

        $this->assertNull($result['resident']);
        $this->assertStringContainsStringIgnoringCase('which resident', $result['answer']);
    }

    public function test_all_medications_lists_active_meds(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, $user, ['name' => 'Metformin 500mg', 'instructions' => 'b.d']);

        $result = app(AssistantService::class)->handle('all medications for Mary Smith', $user);

        $this->assertSame('all_medications', $result['intent']);
        $this->assertStringContainsString('Metformin', $result['answer']);
        $this->assertSame($resident->id, $result['resident']['id']);
    }

    public function test_reply_always_has_resident_key(): void
    {
        Http::fake();
        [, , $user] = $this->seedFixtures();
        $result = app(AssistantService::class)->handle('what meds are due', $user);
        $this->assertArrayHasKey('resident', $result);
        $this->assertNull($result['resident']);
    }
}
