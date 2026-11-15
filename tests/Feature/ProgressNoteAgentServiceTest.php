<?php

namespace Tests\Feature;

use App\Models\AiAssistantLog;
use App\Models\BehaviorChart;
use App\Models\BehaviorChartLog;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\MedicationSynonym;
use App\Models\ProgressNoteDraft;
use App\Models\Resident;
use App\Models\TLog;
use App\Models\User;
use App\Services\AiAssistant\IdentifierScanner;
use App\Services\AiAssistant\MedicationRxNormChecker;
use App\Services\AiAssistant\ChartingRulesRepository;
use App\Services\AiAssistant\ProgressNoteAgentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * These are the deterministic mechanism tests behind the agent design's
 * acceptance targets. They prove the enforcement points are unconditional
 * (true on every run, not probabilistic), which is what actually has to be
 * true for "zero invented medications in 40 drafts" / "12 of 12 events" /
 * "10 of 10 mismatches" to hold over a larger, API-backed eval run — this
 * suite is the thing that run would be checking against, at small scale and
 * without spending real API calls.
 */
class ProgressNoteAgentServiceTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    private User $user;
    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-14 12:00:00', config('app.timezone')));

        $this->createFacilityAndBranch();
        $this->user = $this->createAndActAs('caregiver');
        $this->resident = $this->createResident();

        MedicationSynonym::create(['canonical_name' => 'lisinopril', 'rxnorm_cui' => '29046', 'synonym' => 'lisinopril']);
        MedicationSynonym::create(['canonical_name' => 'acetaminophen', 'rxnorm_cui' => '161', 'synonym' => 'tylenol']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function medication(string $name): Medication
    {
        return Medication::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'instructions' => 'With water',
            'created_by' => $this->user->id,
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);
    }

    private function administration(Medication $medication, string $at, string $status): MedicationAdministration
    {
        return MedicationAdministration::create([
            'medication_id' => $medication->id,
            'resident_id' => $medication->resident_id,
            'branch_id' => $this->branch->id,
            'administered_by' => $this->user->id,
            'administered_at' => Carbon::parse($at, config('app.timezone')),
            'status' => $status,
        ]);
    }

    // --- Medication checker: the "zero invented medications" enforcement point ---

    public function test_medication_checker_blocks_an_unmatched_drug_name(): void
    {
        $checker = new MedicationRxNormChecker();

        $result = $checker->filterMentions([
            ['name' => 'Tylenol'],
            ['name' => 'Zorblatanib'], // not a real drug — nothing should ever let this through
        ], []);

        $this->assertCount(1, $result['matched']);
        $this->assertSame('acetaminophen', $result['matched'][0]['canonical_name']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame('Zorblatanib', $result['blocked'][0]['name']);
    }

    public function test_medication_checker_matches_against_the_residents_own_active_order_first(): void
    {
        $checker = new MedicationRxNormChecker();

        $result = $checker->filterMentions(
            [['name' => 'Metoprolol Tartrate 25mg']],
            [['id' => 1, 'name' => 'Metoprolol Tartrate 25mg']]
        );

        $this->assertCount(1, $result['matched']);
        $this->assertSame('active_order', $result['matched'][0]['matched_via']);
    }

    public function test_medication_checker_flags_order_with_no_logged_administration(): void
    {
        $checker = new MedicationRxNormChecker();

        $flags = $checker->checkOrderVsLog(
            [['id' => 1, 'name' => 'Lisinopril']],
            []
        );

        $this->assertCount(1, $flags);
        $this->assertSame('order_without_log', $flags[0]['type']);
    }

    public function test_medication_checker_flags_administration_without_a_matching_active_order(): void
    {
        $checker = new MedicationRxNormChecker();

        $flags = $checker->checkOrderVsLog(
            [],
            [['medication_id' => 99, 'medication_name' => 'Warfarin', 'status' => 'completed']]
        );

        $this->assertCount(1, $flags);
        $this->assertSame('log_without_active_order', $flags[0]['type']);
    }

    public function test_medication_checker_catches_every_planted_mismatch_in_a_batch(): void
    {
        // Ten planted disagreements: five orders never logged, five logs with no active order.
        $checker = new MedicationRxNormChecker();

        $orders = collect(range(1, 5))->map(fn ($i) => ['id' => $i, 'name' => "Drug Order {$i}"])->all();
        $orphanLogs = collect(range(101, 105))->map(fn ($i) => [
            'medication_id' => $i, 'medication_name' => "Orphan Drug {$i}", 'status' => 'completed',
        ])->all();

        $flags = $checker->checkOrderVsLog($orders, $orphanLogs);

        $this->assertCount(10, $flags, 'All 10 planted mismatches must be caught — none silently dropped.');
    }

    // --- Identifier scanning ---

    public function test_identifier_scanner_detects_a_known_name_and_redacts_it(): void
    {
        $hits = IdentifierScanner::scan('Jane Smith refused her morning dose.', ['Jane Smith']);
        $this->assertNotEmpty($hits);
        $this->assertSame('known_name', $hits[0]['type']);

        $redacted = IdentifierScanner::redactKnownNames('Jane Smith refused her morning dose.', ['Jane Smith']);
        $this->assertStringNotContainsString('Jane', $redacted);
        $this->assertStringContainsString('{RESIDENT}', $redacted);
    }

    public function test_identifier_scanner_detects_ssn_like_and_email_patterns(): void
    {
        $hits = IdentifierScanner::scan('Contact 555-123-4567 or jane@example.com, SSN 123-45-6789.');
        $types = array_column($hits, 'type');

        $this->assertContains('ssn_like', $types);
        $this->assertContains('email', $types);
        $this->assertContains('phone_like', $types);
    }

    // --- Neutral language ---

    public function test_charting_rules_repository_flags_judgmental_terms(): void
    {
        $terms = ChartingRulesRepository::judgmentalTerms();
        $this->assertContains('uncooperative', $terms);
        $this->assertContains('manipulative', $terms);
    }

    public function test_charting_rules_search_returns_relevant_rule_for_medication_topic(): void
    {
        $results = ChartingRulesRepository::search('medication');
        $ids = array_column($results, 'id');
        $this->assertContains('medication_discrepancy', $ids);
    }

    // --- The agent service end to end ---

    public function test_fixed_sequence_fallback_never_invents_a_medication_when_ai_is_disabled(): void
    {
        config(['services.anthropic.enabled' => false]);
        Http::fake(); // nothing should be sent at all

        $lisinopril = $this->medication('Lisinopril');
        $this->administration($lisinopril, '2026-11-12 08:00:00', 'completed');

        $service = app(ProgressNoteAgentService::class);
        $result = $service->draft($this->resident, 'Gave Lisinopril and some Zorblatanib this morning.', $this->user);

        Http::assertNothingSent();
        $this->assertSame('heuristic', $result['mode']);

        $medNames = array_column($result['draft']['medications'], 'mentioned_as');
        $this->assertContains('Lisinopril', $medNames);
        // The fixed-sequence fallback only ever proposes names it already recognizes
        // (active orders) — so an invented drug name is never proposed in the first
        // place, let alone surfaced. That's a stronger guarantee than "blocked",
        // which is why there is nothing to find in medication_check.blocked here.
        $this->assertNotContains('Zorblatanib', $medNames, 'An invented/unverifiable drug must never reach the draft.');
        $this->assertEmpty($result['medication_check']['blocked']);
    }

    public function test_tool_use_loop_filters_an_invented_medication_the_model_proposes(): void
    {
        config(['services.anthropic.enabled' => true]);
        config(['services.anthropic.api_key' => 'sk-test-key']);

        $lisinopril = $this->medication('Lisinopril');
        $this->administration($lisinopril, '2026-11-12 08:00:00', 'completed');

        // The model skips tool use and answers directly, proposing one real
        // and one fabricated medication — proving the PHP filter (not model
        // self-restraint) is what keeps the fake one out.
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'draft' => [
                            'narrative' => 'The resident received morning medications as scheduled.',
                            'events' => [['text' => 'Morning medication round completed', 'source' => 'medication_log']],
                            'medications_proposed' => [
                                ['name' => 'Lisinopril', 'context' => 'given at 08:00'],
                                ['name' => 'Zorblatanib', 'context' => 'invented by the model'],
                            ],
                        ],
                        'summary' => [
                            'text' => 'No urgent concerns in the last 7 days.',
                            'flags' => [],
                        ],
                    ]),
                ]],
            ], 200),
        ]);

        $service = app(ProgressNoteAgentService::class);
        $result = $service->draft($this->resident, 'Gave Lisinopril this morning.', $this->user);

        $this->assertSame('anthropic', $result['mode']);

        $medNames = array_column($result['draft']['medications'], 'mentioned_as');
        $this->assertContains('Lisinopril', $medNames);
        $this->assertNotContains('Zorblatanib', $medNames);

        $flagTypes = array_column($result['summary']['flags'], 'type');
        $this->assertContains('unmatched_medication_mention', $flagTypes);
        $this->assertSame('flagged', $result['status']);
    }

    public function test_tool_use_loop_executes_a_requested_tool_call_before_finishing(): void
    {
        config(['services.anthropic.enabled' => true]);
        config(['services.anthropic.api_key' => 'sk-test-key']);

        Http::fakeSequence()
            ->push([
                'stop_reason' => 'tool_use',
                'content' => [[
                    'type' => 'tool_use',
                    'id' => 'tool_1',
                    'name' => 'rules_search',
                    'input' => ['query' => 'neutral language'],
                ]],
            ], 200)
            ->push([
                'stop_reason' => 'end_turn',
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'draft' => ['narrative' => 'Not documented', 'events' => [], 'medications_proposed' => []],
                        'summary' => ['text' => 'Not documented', 'flags' => []],
                    ]),
                ]],
            ], 200);

        $service = app(ProgressNoteAgentService::class);
        $result = $service->draft($this->resident, null, $this->user);

        $this->assertSame('anthropic', $result['mode']);

        $log = AiAssistantLog::where('feature', 'progress_note_draft')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, $log->tool_call_count);
        $this->assertSame('rules_search', $log->tool_calls[0]['tool']);
    }

    public function test_every_planted_behavior_event_appears_in_the_fallback_draft(): void
    {
        config(['services.anthropic.enabled' => false]);

        $chart = BehaviorChart::create([
            'resident_id' => $this->resident->id,
            'caregiver_id' => $this->user->id,
            'chart_date' => '2026-11-13',
            'status' => 'submitted',
        ]);

        $plantedEvents = ['Paced hallway before lunch', 'Raised voice at breakfast', 'Refused afternoon activity'];
        foreach ($plantedEvents as $description) {
            BehaviorChartLog::create([
                'behavior_chart_id' => $chart->id,
                'occurred_at' => '2026-11-13 10:00:00',
                'behavior_description' => $description,
                'reported_to_provider' => false,
            ]);
        }

        $service = app(ProgressNoteAgentService::class);
        $result = $service->draft($this->resident, null, $this->user);

        $eventTexts = implode(' | ', array_column($result['draft']['events'], 'text'));
        foreach ($plantedEvents as $description) {
            $this->assertStringContainsString($description, $eventTexts, "Planted event \"{$description}\" must survive into the draft.");
        }
    }

    public function test_an_ai_assistant_log_row_is_written_for_every_draft(): void
    {
        config(['services.anthropic.enabled' => false]);

        $this->assertDatabaseCount('ai_assistant_logs', 0);

        app(ProgressNoteAgentService::class)->draft($this->resident, 'Resident ate well today.', $this->user);

        $this->assertDatabaseCount('ai_assistant_logs', 1);
        $log = AiAssistantLog::first();
        $this->assertSame('progress_note_draft', $log->feature);
        $this->assertSame($this->resident->id, $log->resident_id);
    }

    // --- Controller: review gating ---

    public function test_generating_a_draft_never_creates_a_t_log(): void
    {
        $this->assertDatabaseCount('t_logs', 0);

        $this->postJson("/api/v1/residents/{$this->resident->id}/ai-progress-notes", [
            'shift_entry' => 'Resident was calm and ate well.',
        ])->assertCreated();

        $this->assertDatabaseCount('t_logs', 0);
        $this->assertDatabaseCount('progress_note_drafts', 1);
    }

    public function test_approve_is_rejected_without_an_explicit_confirmation(): void
    {
        $admin = $this->createAndActAs('administrator');
        $draft = ProgressNoteDraft::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'created_by' => $admin->id,
            'draft_json' => ['narrative' => 'Draft text', 'events' => [], 'medications' => []],
            'summary_json' => ['text' => 'Summary', 'flags' => []],
            'mode' => 'heuristic',
            'status' => ProgressNoteDraft::STATUS_PENDING_REVIEW,
        ]);

        $this->postJson("/api/v1/residents/{$this->resident->id}/ai-progress-notes/{$draft->id}/approve", [])
            ->assertStatus(422);

        $this->assertDatabaseCount('t_logs', 0);
        $this->assertSame(ProgressNoteDraft::STATUS_PENDING_REVIEW, $draft->fresh()->status);
    }

    public function test_approve_with_confirmation_creates_a_t_log_and_marks_the_draft_approved(): void
    {
        $admin = $this->createAndActAs('administrator');
        $draft = ProgressNoteDraft::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'created_by' => $admin->id,
            'draft_json' => ['narrative' => 'Resident had a stable shift.', 'events' => [], 'medications' => []],
            'summary_json' => ['text' => 'Summary', 'flags' => []],
            'mode' => 'heuristic',
            'status' => ProgressNoteDraft::STATUS_PENDING_REVIEW,
        ]);

        $this->postJson("/api/v1/residents/{$this->resident->id}/ai-progress-notes/{$draft->id}/approve", [
            'confirmed' => true,
        ])->assertOk();

        $this->assertDatabaseCount('t_logs', 1);
        $draft->refresh();
        $this->assertSame(ProgressNoteDraft::STATUS_APPROVED, $draft->status);
        $this->assertNotNull($draft->t_log_id);
        $this->assertSame($admin->id, $draft->reviewed_by);
    }

    public function test_a_caregiver_cannot_approve_a_draft(): void
    {
        $draft = ProgressNoteDraft::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'created_by' => $this->user->id,
            'draft_json' => ['narrative' => 'x', 'events' => [], 'medications' => []],
            'summary_json' => ['text' => 'x', 'flags' => []],
            'mode' => 'heuristic',
            'status' => ProgressNoteDraft::STATUS_PENDING_REVIEW,
        ]);

        $this->postJson("/api/v1/residents/{$this->resident->id}/ai-progress-notes/{$draft->id}/approve", [
            'confirmed' => true,
        ])->assertStatus(403);
    }

    public function test_manager_queue_only_lists_flagged_drafts_and_requires_admin_role(): void
    {
        ProgressNoteDraft::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'created_by' => $this->user->id,
            'draft_json' => ['narrative' => 'x', 'events' => [], 'medications' => []],
            'summary_json' => ['text' => 'x', 'flags' => []],
            'mode' => 'heuristic',
            'status' => ProgressNoteDraft::STATUS_PENDING_REVIEW,
        ]);
        ProgressNoteDraft::create([
            'resident_id' => $this->resident->id,
            'branch_id' => $this->branch->id,
            'facility_id' => $this->facility->id,
            'created_by' => $this->user->id,
            'draft_json' => ['narrative' => 'y', 'events' => [], 'medications' => []],
            'summary_json' => ['text' => 'y', 'flags' => [['type' => 'x', 'severity' => 'high', 'detail' => 'x']]],
            'mode' => 'heuristic',
            'status' => ProgressNoteDraft::STATUS_FLAGGED,
        ]);

        $this->getJson('/api/v1/ai-progress-notes/queue')->assertStatus(403);

        $this->createAndActAs('administrator');
        $response = $this->getJson('/api/v1/ai-progress-notes/queue')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('flagged', $response->json('data.0.status'));
    }
}
