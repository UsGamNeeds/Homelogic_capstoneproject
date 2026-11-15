<?php

namespace App\Services\AiAssistant;

use App\Models\AiAssistantLog;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Drafts a progress note from a caregiver's rough shift entry, plus a 7-day
 * follow-up summary with flags — and nothing more than that. Nothing this
 * class produces is saved as a real record or acted on; it only ever returns
 * a draft for AiProgressNoteController to persist as a ProgressNoteDraft,
 * which a human must separately approve (see that controller) before a real
 * t_logs row is ever created.
 *
 * Shares the same AI_ASSISTANT_ENABLED / ANTHROPIC_API_KEY gating as
 * ChartAssistantService (one master switch for all outbound AI traffic), but
 * is otherwise a standalone service so the existing progress-note AI path
 * (ChartAssistantController::generateProgressNote, used by the Chart
 * Assistant panel) is not touched by anything here.
 *
 * Model is pinned to Claude Haiku 4.5 for this feature specifically, per the
 * agent design spec, independent of the general ANTHROPIC_MODEL setting.
 */
class ProgressNoteAgentService
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MAX_TOOL_ITERATIONS = 6;

    public function __construct(
        private ResidentRecordsTool $recordsTool,
        private MedicationRxNormChecker $medicationChecker,
    ) {
    }

    public function draft(Resident $resident, ?string $roughEntry, User $user): array
    {
        $records = $this->recordsTool->fetch($resident);
        $knownNames = $this->residentNameVariants($resident);
        $redactedEntry = $roughEntry ? IdentifierScanner::redactKnownNames($roughEntry, $knownNames) : null;

        $activeOrders = array_map(
            fn ($m) => ['id' => $m['id'], 'name' => $m['name']],
            $records['medications']['active']
        );

        $toolLog = [];
        $enabled = (bool) config('services.anthropic.enabled', false);
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

        $result = null;
        $mode = 'heuristic';
        $model = null;

        if ($enabled && ! empty($apiKey)) {
            try {
                [$result, $toolLog] = $this->runAgentLoop($apiKey, $redactedEntry, $records, $activeOrders);
                $mode = 'anthropic';
                $model = self::MODEL;
            } catch (\Throwable $e) {
                Log::warning('Progress note agent fallback triggered', ['message' => $e->getMessage()]);
            }
        } else {
            Log::warning('Progress note agent call skipped', [
                'message' => 'AI assistant is disabled by configuration or no API key is present; using the fixed-sequence fallback.',
            ]);
        }

        if ($result === null) {
            $result = $this->fixedSequenceFallback($redactedEntry, $records, $activeOrders);
            $mode = 'heuristic';
            $model = null;
        }

        // The medication checker is the ground truth regardless of mode —
        // whatever the model proposed only survives if it resolves here.
        $mentionCheck = $this->medicationChecker->filterMentions(
            $result['draft']['medications_proposed'] ?? [],
            $activeOrders
        );
        $orderVsLogFlags = $this->medicationChecker->checkOrderVsLog(
            $activeOrders,
            $records['medications']['administrations']
        );

        $flags = array_merge(
            $result['summary']['flags'] ?? [],
            $orderVsLogFlags,
            array_map(fn ($b) => [
                'type' => 'unmatched_medication_mention',
                'severity' => 'high',
                'detail' => "\"{$b['name']}\" was mentioned but does not match any known medication for this resident — removed from the draft, needs manual review.",
            ], $mentionCheck['blocked'])
        );

        $narrative = (string) ($result['draft']['narrative'] ?? 'Not documented');
        $summaryText = (string) ($result['summary']['text'] ?? 'Not documented');

        $judgmentalHits = $this->scanJudgmentalLanguage($narrative.' '.$summaryText);
        if (! empty($judgmentalHits)) {
            $flags[] = [
                'type' => 'neutral_language_review',
                'severity' => 'medium',
                'detail' => 'Possible judgmental language found: '.implode(', ', $judgmentalHits).'. Review wording before approving.',
            ];
        }

        $identifierHits = IdentifierScanner::scan($narrative.' '.$summaryText, $knownNames);
        if (! empty($identifierHits)) {
            $flags[] = [
                'type' => 'identifier_detected',
                'severity' => 'high',
                'detail' => 'Possible identifying information found in the drafted text; review before approving.',
            ];
        }

        $draft = [
            'narrative' => $narrative,
            'events' => $result['draft']['events'] ?? [],
            'medications' => array_map(fn ($m) => [
                'name' => $m['canonical_name'],
                'mentioned_as' => $m['mentioned_as'],
                'rxnorm_cui' => $m['rxnorm_cui'],
                'source' => 'medication_log',
            ], $mentionCheck['matched']),
        ];

        $summary = [
            'window_label' => $records['window_days'].'-day follow-up summary',
            'text' => $summaryText,
            'flags' => $flags,
        ];

        $status = empty($flags) ? 'pending_review' : 'flagged';

        $this->logCall($resident, $user, $mode, $model, $toolLog, $redactedEntry, $draft, $summary, $identifierHits, $flags);

        return [
            'label' => 'Draft – human review required',
            'mode' => $mode,
            'model' => $model,
            'status' => $status,
            'draft' => $draft,
            'summary' => $summary,
            'medication_check' => [
                'matched' => $mentionCheck['matched'],
                'blocked' => $mentionCheck['blocked'],
                'order_vs_log_flags' => $orderVsLogFlags,
            ],
        ];
    }

    /**
     * @return array{0: array, 1: array} [parsedResult, toolCallLog]
     */
    private function runAgentLoop(string $apiKey, ?string $redactedEntry, array $records, array $activeOrders): array
    {
        $toolLog = [];
        $messages = [[
            'role' => 'user',
            'content' => $this->buildUserPrompt($redactedEntry),
        ]];

        $tools = $this->toolDefinitions();

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => self::MODEL,
                    'max_tokens' => 2048,
                    'system' => $this->systemPrompt(),
                    'tools' => $tools,
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException(sprintf(
                    'Progress note agent request failed (HTTP %d): %s',
                    $response->status(),
                    $response->body()
                ));
            }

            $content = $response->json('content', []);
            $stopReason = $response->json('stop_reason');

            $messages[] = ['role' => 'assistant', 'content' => $content];

            if ($stopReason !== 'tool_use') {
                $text = collect($content)->firstWhere('type', 'text')['text'] ?? null;
                if (! is_string($text)) {
                    throw new \RuntimeException('Progress note agent returned no text content.');
                }

                $parsed = json_decode($this->extractJson($text), true);
                if (! is_array($parsed)) {
                    throw new \RuntimeException('Progress note agent response was not valid JSON: '.substr($text, 0, 500));
                }

                return [$parsed, $toolLog];
            }

            $toolResults = [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                $toolName = $block['name'];
                $toolInput = $block['input'] ?? [];
                $output = $this->dispatchTool($toolName, $toolInput, $records, $activeOrders);
                $toolLog[] = ['tool' => $toolName, 'input' => $toolInput];

                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($output),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        throw new \RuntimeException('Progress note agent exceeded the maximum tool-call iterations.');
    }

    private function dispatchTool(string $name, array $input, array $records, array $activeOrders): array
    {
        return match ($name) {
            'resident_records' => $records,
            'rules_search' => ['rules' => ChartingRulesRepository::search((string) ($input['query'] ?? ''))],
            'medication_check' => $this->medicationChecker->filterMentions(
                array_map(fn ($n) => ['name' => $n], $input['mentions'] ?? []),
                $activeOrders
            ),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    /** Fixed, deterministic order: records already fetched, then medication check, then rules — no AI involved. */
    private function fixedSequenceFallback(?string $redactedEntry, array $records, array $activeOrders): array
    {
        $mentions = $this->extractKnownMedicationMentions($redactedEntry ?? '', $activeOrders);
        $this->medicationChecker->filterMentions($mentions, $activeOrders); // run for parity/logging; result re-derived by caller
        ChartingRulesRepository::search('neutral language gaps source');

        $events = [];
        foreach ($records['behavior_events'] as $event) {
            $events[] = [
                'text' => ($event['description'] ?: 'Behavior event').' ('.($event['occurred_at'] ?? 'time not documented').')',
                'source' => 'behavior_chart',
            ];
        }

        $narrativeParts = [];
        $narrativeParts[] = $redactedEntry
            ? 'Shift entry: '.trim($redactedEntry)
            : 'Not documented: no shift entry was provided for this draft.';

        if ($records['vitals']['count'] > 0) {
            $narrativeParts[] = "{$records['vitals']['count']} vitals reading(s) recorded in the window.";
        } else {
            $narrativeParts[] = 'Vitals: Not documented in this window.';
        }

        if (! empty($records['behavior_events'])) {
            $narrativeParts[] = count($records['behavior_events']).' behavior chart event(s) recorded.';
        }

        $missed = $records['medications']['missed_count'];
        $narrativeParts[] = $missed > 0
            ? "{$missed} medication administration(s) were recorded as missed or refused in this window."
            : 'No missed or refused medication administrations recorded in this window.';

        return [
            'draft' => [
                'narrative' => implode(' ', $narrativeParts),
                'events' => $events,
                'medications_proposed' => $mentions,
            ],
            'summary' => [
                'text' => $this->buildHeuristicSummaryText($records),
                'flags' => [],
            ],
        ];
    }

    private function buildHeuristicSummaryText(array $records): string
    {
        $parts = [];
        $parts[] = "{$records['vitals']['count']} vitals entries in the last {$records['window_days']} days";
        $parts[] = "{$records['medications']['missed_count']} missed/refused medication administration(s)";
        $parts[] = count($records['behavior_events']).' behavior event(s)';
        $parts[] = "{$records['appointments']['upcoming_count']} upcoming appointment(s)";

        return ucfirst(implode(', ', $parts)).'.';
    }

    /** Matches only against known vocabulary (active orders + synonym table) — never invents a drug name. */
    private function extractKnownMedicationMentions(string $text, array $activeOrders): array
    {
        $found = [];
        $haystack = strtolower($text);

        foreach ($activeOrders as $order) {
            if ($order['name'] && str_contains($haystack, strtolower($order['name']))) {
                $found[] = ['name' => $order['name'], 'context' => null];
            }
        }

        return $found;
    }

    private function scanJudgmentalLanguage(string $text): array
    {
        $lower = strtolower($text);
        $hits = [];
        foreach (ChartingRulesRepository::judgmentalTerms() as $term) {
            if (str_contains($lower, $term)) {
                $hits[] = $term;
            }
        }

        return $hits;
    }

    private function residentNameVariants(Resident $resident): array
    {
        $first = trim((string) $resident->first_name);
        $last = trim((string) $resident->last_name);

        return array_values(array_filter([trim("$first $last"), $first, $last]));
    }

    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'resident_records',
                'description' => 'Returns the resident\'s vitals, sleep, behavior events, appointments, and medication orders/administrations for the review window. No input needed.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
            ],
            [
                'name' => 'rules_search',
                'description' => 'Search the facility charting-standards rules (neutral language, source tagging, gap handling, etc.) for guidance relevant to a topic.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => 'Topic to search for, e.g. "medication" or "neutral language".']],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'medication_check',
                'description' => 'Verify one or more medication names mentioned in the shift entry against the resident\'s known medications. Call this before stating that any medication was given or discussed — never state a medication fact without calling this first.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'mentions' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Drug names as written in the shift entry.'],
                    ],
                    'required' => ['mentions'],
                ],
            ],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a resident-care charting assistant. You draft a progress note from a
caregiver's rough shift entry and produce a short follow-up summary. You do
not save or act on anything — a human always reviews your draft before it
becomes a real record.

Rules:
- Call medication_check before stating any medication was given, discussed,
  or should be reviewed. Never assert a medication fact you have not verified
  with that tool.
- Call resident_records before writing the summary so it reflects the actual
  window data, not just the shift entry.
- Use rules_search if you are unsure how to phrase something.
- Use neutral, observational language — describe what happened, not an
  interpretation of the resident's character or intent.
- Never state a medical diagnosis.
- Where the data has no information for a section, write "Not documented".
- Refer to the resident only as "the resident" — never by name.
- Every discrete event you include in "events" must carry a "source" field
  identifying where it came from (e.g. "shift_entry", "vitals", "behavior_chart",
  "medication_log").

When you are done, reply with ONLY a single JSON object (no prose, no code
fence) matching this shape:
{
  "draft": {
    "narrative": "string — the drafted progress note",
    "events": [{"text": "string", "source": "string"}],
    "medications_proposed": [{"name": "string as mentioned", "context": "string or null"}]
  },
  "summary": {
    "text": "string — 7-day follow-up summary",
    "flags": [{"type": "string", "severity": "low|medium|high", "detail": "string"}]
  }
}
PROMPT;
    }

    private function buildUserPrompt(?string $redactedEntry): string
    {
        return json_encode([
            'shift_entry' => $redactedEntry ?: '(none provided — summarize from records only)',
            'instructions' => 'Draft the progress note and 7-day summary per the system prompt. Use your tools first.',
        ]);
    }

    private function extractJson(string $content): string
    {
        $trimmed = trim($content);

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            return $matches[0];
        }

        return $trimmed;
    }

    private function logCall(
        Resident $resident,
        User $user,
        string $mode,
        ?string $model,
        array $toolLog,
        ?string $redactedEntry,
        array $draft,
        array $summary,
        array $identifierHits,
        array $flags
    ): void {
        try {
            AiAssistantLog::create([
                'facility_id' => $resident->branch?->facility_id,
                'resident_id' => $resident->id,
                'user_id' => $user->id,
                'feature' => 'progress_note_draft',
                'mode' => $mode,
                'model' => $model,
                'tool_call_count' => count($toolLog),
                'tool_calls' => $toolLog,
                'prompt_redacted' => $redactedEntry,
                'response_redacted' => json_encode(['draft' => $draft, 'summary' => $summary]),
                'identifier_scan_hits' => $identifierHits,
                'flags' => $flags,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write ai_assistant_logs row', ['message' => $e->getMessage()]);
        }
    }
}
