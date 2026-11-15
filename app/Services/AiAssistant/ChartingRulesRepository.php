<?php

namespace App\Services\AiAssistant;

/**
 * Small, curated charting-standards reference the progress-note agent can
 * search (the "rules search" tool) and that the draft gets checked against
 * afterward. Deliberately static PHP, not AI-generated — these are the rules
 * the AI must follow, so they can't depend on the AI to know them correctly.
 */
class ChartingRulesRepository
{
    /** @return array<int, array{id: string, keywords: array<string>, text: string}> */
    public static function rules(): array
    {
        return [
            [
                'id' => 'neutral_language',
                'keywords' => ['language', 'tone', 'judg', 'behavior', 'behaviour'],
                'text' => 'Describe what was observed, not an interpretation of character or intent. '
                    .'Write "resident raised voice and refused medication" rather than "resident was '
                    .'difficult" or "resident was uncooperative".',
            ],
            [
                'id' => 'no_diagnosis',
                'keywords' => ['diagnos', 'medical', 'assess', 'condition'],
                'text' => 'Do not state or imply a medical diagnosis. Report observed signs/symptoms and '
                    .'let clinical staff draw conclusions (e.g. "resident reported chest tightness" not '
                    .'"resident appears to be having a cardiac event").',
            ],
            [
                'id' => 'gaps_not_documented',
                'keywords' => ['gap', 'missing', 'unknown', 'not documented', 'incomplete'],
                'text' => 'When the source data has no information for a section, write "Not documented" '
                    .'rather than omitting the section or guessing a plausible value.',
            ],
            [
                'id' => 'source_every_claim',
                'keywords' => ['source', 'citation', 'evidence', 'attribut'],
                'text' => 'Every medication name, dose, and discrete event in the note must carry a source '
                    .'tag identifying where it came from (shift entry, medication log, vitals, resident '
                    .'record). Do not state a fact that cannot be traced to one of those sources.',
            ],
            [
                'id' => 'falls_and_incidents',
                'keywords' => ['fall', 'incident', 'injury', 'accident'],
                'text' => 'Any fall, injury, or unwitnessed event mentioned in the shift entry must be '
                    .'flagged for follow-up, even if the entry describes it as minor.',
            ],
            [
                'id' => 'medication_discrepancy',
                'keywords' => ['medication', 'drug', 'dose', 'missed', 'refused'],
                'text' => 'Never state that a medication was given unless it is corroborated by the '
                    .'medication administration log. A mention in free text alone is not sufficient.',
            ],
            [
                'id' => 'time_stamps',
                'keywords' => ['time', 'when', 'shift'],
                'text' => 'Reference the shift/time window the entry covers; do not imply events happened '
                    .'"today" without tying them to the specific date in the data provided.',
            ],
            [
                'id' => 'phi_minimization',
                'keywords' => ['privacy', 'phi', 'identity', 'name'],
                'text' => 'Refer to the resident as "the resident", never by name, and never name other '
                    .'residents, staff by full name, or include contact details in the note body.',
            ],
        ];
    }

    /** @return array<int, array{id: string, text: string}> */
    public static function search(string $query): array
    {
        $needle = strtolower(trim($query));
        if ($needle === '') {
            return array_map(fn ($r) => ['id' => $r['id'], 'text' => $r['text']], self::rules());
        }

        $matches = array_values(array_filter(self::rules(), function (array $rule) use ($needle) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($needle, $keyword) || str_contains($keyword, $needle)) {
                    return true;
                }
            }

            return str_contains(strtolower($rule['text']), $needle);
        }));

        if (empty($matches)) {
            $matches = self::rules();
        }

        return array_map(fn ($r) => ['id' => $r['id'], 'text' => $r['text']], $matches);
    }

    /** Terms the neutral-language check flags for human review rather than silently rewriting. */
    public static function judgmentalTerms(): array
    {
        return [
            'difficult', 'uncooperative', 'manipulative', 'attention-seeking', 'attention seeking',
            'combative', 'lazy', 'demanding', 'bad attitude', 'faking', 'drama', 'dramatic',
            'non-compliant', 'noncompliant', 'stubborn', 'nasty', 'rude',
        ];
    }
}
