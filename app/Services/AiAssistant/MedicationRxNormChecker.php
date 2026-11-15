<?php

namespace App\Services\AiAssistant;

use App\Models\MedicationSynonym;

/**
 * Deterministic, non-AI medication verification. Nothing about which drugs
 * exist or were given is ever taken on the model's word — this class is the
 * single gate that decides what's allowed to appear in a drafted note.
 *
 * Two jobs:
 *   1. match()/filterMentions() — resolve a free-text drug name to a known
 *      medication (the resident's own active orders first, then the global
 *      synonym table) and BLOCK anything that doesn't resolve, rather than
 *      letting it through unverified.
 *   2. checkOrderVsLog() — compare active orders against the administration
 *      log for the same window and flag disagreements (ordered but never
 *      logged, logged but not currently on an active order).
 */
class MedicationRxNormChecker
{
    /** @var array<string, array{canonical_name: string, rxnorm_cui: ?string}> */
    private array $synonymIndex;

    public function __construct(?array $synonymIndex = null)
    {
        $this->synonymIndex = $synonymIndex ?? $this->loadSynonymIndex();
    }

    private function loadSynonymIndex(): array
    {
        return MedicationSynonym::all(['synonym', 'canonical_name', 'rxnorm_cui'])
            ->mapWithKeys(fn ($row) => [
                $this->normalize($row->synonym) => [
                    'canonical_name' => $row->canonical_name,
                    'rxnorm_cui' => $row->rxnorm_cui,
                ],
            ])
            ->all();
    }

    public function normalize(string $name): string
    {
        $name = strtolower(trim($name));
        // Strip common dose/route/frequency noise so "Lisinopril 10mg" and
        // "lisinopril" resolve to the same key.
        $name = preg_replace('/\b\d+(\.\d+)?\s?(mg|mcg|g|ml|units?)\b/', '', $name) ?? $name;
        $name = preg_replace('/[^a-z\s-]/', '', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /**
     * Resolve a free-text name against (in order of trust): the resident's
     * own active orders, then the global synonym table. Returns null —
     * meaning "block it" — when neither source recognizes the name.
     *
     * @param array<int, array{id: int|string, name: string}> $activeOrders
     */
    public function match(string $rawName, array $activeOrders = []): ?array
    {
        $normalized = $this->normalize($rawName);
        if ($normalized === '') {
            return null;
        }

        foreach ($activeOrders as $order) {
            if ($this->normalize((string) $order['name']) === $normalized) {
                return [
                    'canonical_name' => $order['name'],
                    'rxnorm_cui' => null,
                    'matched_via' => 'active_order',
                    'medication_id' => $order['id'] ?? null,
                ];
            }
        }

        if (isset($this->synonymIndex[$normalized])) {
            return $this->synonymIndex[$normalized] + ['matched_via' => 'synonym_table', 'medication_id' => null];
        }

        // Loose contains-match for things like "Lasix 40" vs seeded "lasix".
        foreach ($this->synonymIndex as $synonym => $entry) {
            if ($synonym !== '' && (str_contains($normalized, $synonym) || str_contains($synonym, $normalized))) {
                return $entry + ['matched_via' => 'synonym_table_fuzzy', 'medication_id' => null];
            }
        }

        return null;
    }

    /**
     * Filters a list of AI-proposed medication mentions down to only the
     * ones that resolve to a known drug. This is the enforcement point for
     * "zero invented medications" — whatever doesn't survive this call is
     * never written into the draft, only recorded as a flag.
     *
     * @param array<int, array{name: string, context?: string}> $proposedMentions
     * @param array<int, array{id: int|string, name: string}> $activeOrders
     * @return array{matched: array, blocked: array}
     */
    public function filterMentions(array $proposedMentions, array $activeOrders = []): array
    {
        $matched = [];
        $blocked = [];

        foreach ($proposedMentions as $mention) {
            $name = is_array($mention) ? ($mention['name'] ?? '') : (string) $mention;
            $context = is_array($mention) ? ($mention['context'] ?? null) : null;

            if (trim((string) $name) === '') {
                continue;
            }

            $resolved = $this->match((string) $name, $activeOrders);

            if ($resolved === null) {
                $blocked[] = ['name' => $name, 'context' => $context];

                continue;
            }

            $matched[] = array_merge($resolved, ['mentioned_as' => $name, 'context' => $context]);
        }

        return ['matched' => $matched, 'blocked' => $blocked];
    }

    /**
     * Compares the resident's active orders against what was actually
     * logged in the same window and flags disagreements. Entirely table
     * lookups — no AI, no free text.
     *
     * @param array<int, array{id: int|string, name: string}> $activeOrders
     * @param array<int, array{medication_id: int|string|null, medication_name: ?string, status: string}> $administrations
     */
    public function checkOrderVsLog(array $activeOrders, array $administrations): array
    {
        $flags = [];

        $loggedMedicationIds = array_unique(array_filter(array_map(
            fn ($a) => $a['medication_id'] ?? null,
            $administrations
        )));

        foreach ($activeOrders as $order) {
            if (! in_array($order['id'], $loggedMedicationIds, false)) {
                $flags[] = [
                    'type' => 'order_without_log',
                    'severity' => 'medium',
                    'detail' => "{$order['name']} is an active order with no administration logged in this window.",
                ];
            }
        }

        $activeOrderIds = array_map(fn ($o) => $o['id'], $activeOrders);
        foreach ($administrations as $admin) {
            if ($admin['medication_id'] !== null && ! in_array($admin['medication_id'], $activeOrderIds, false)) {
                $flags[] = [
                    'type' => 'log_without_active_order',
                    'severity' => 'high',
                    'detail' => ($admin['medication_name'] ?? 'A medication').' was logged but is not currently an active order — verify it was not discontinued or logged for the wrong resident.',
                ];
            }
        }

        $missed = array_filter($administrations, fn ($a) => in_array($a['status'] ?? null, ['missed', 'refused'], true));
        foreach ($missed as $admin) {
            $flags[] = [
                'type' => 'missed_or_refused',
                'severity' => 'medium',
                'detail' => ($admin['medication_name'] ?? 'A medication')." was recorded as {$admin['status']} on ".($admin['administered_at'] ?? 'an unspecified date').'.',
            ];
        }

        return $flags;
    }
}
