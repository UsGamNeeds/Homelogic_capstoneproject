<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;

class AssistantService
{
    public function __construct(
        private readonly ResidentResolver $resolver,
        private readonly IntentParsingService $intents,
        private readonly MedicationService $medications,
    ) {}

    /**
     * @return array{intent: string, answer: string, data: array, suggestions: array}
     */
    public function handle(string $message, User $user): array
    {
        $resolution = $this->resolver->resolve($message, $user);

        if ($resolution->status === 'none') {
            // No resident matched: never send the raw name to the LLM. Classify
            // locally only, and ask for a resident if this looks med-related.
            $intent = $this->intents->fastPath($message);
            if ($intent === 'todays_meds' || $intent === 'prn_status') {
                return $this->reply($intent, "Which resident? I couldn't find that name in your facility.");
            }

            return $this->reply('unknown', "I can tell you a resident's medications for today, or their PRN (as-needed) meds. Try: \"today's meds for Jane Doe\".");
        }

        if ($resolution->status === 'ambiguous') {
            $names = collect($resolution->matches)->map(fn ($r) => $this->label($r))->implode(', ');
            $suggestions = collect($resolution->matches)->map(fn ($r) => [
                'label' => $this->label($r),
                'message' => "today's meds for {$this->label($r)}",
            ])->all();

            return $this->reply('unknown', "More than one resident matches that name. Which one did you mean: {$names}?", [], $suggestions);
        }

        /** @var Resident $resident */
        $resident = $resolution->matches[0];
        $intent = $this->intents->parse($resolution->redacted);

        return match ($intent) {
            'todays_meds' => $this->answerTodaysMeds($resident),
            'prn_status' => $this->answerPrn($resident),
            default => $this->reply(
                'unknown',
                "I can help with {$this->label($resident)}'s meds for today, or their PRN meds. Which would you like?",
                [],
                $this->choiceSuggestions($resident),
            ),
        };
    }

    private function answerTodaysMeds(Resident $resident): array
    {
        $meds = $resident->medicationOrders()
            ->where('is_active', true)
            ->with(['administrations' => fn ($q) => $q->whereDate('administered_at', today())])
            ->get();

        if ($meds->isEmpty()) {
            return $this->reply('todays_meds', "No active medications on record for {$this->label($resident)}.");
        }

        $withStatus = $this->medications->getMedicationsWithStatus($meds, today());

        $lines = $withStatus->map(function ($m) {
            if ($m->is_fully_administered_today) {
                $status = 'all doses given today';
            } elseif ($m->is_overdue) {
                $status = 'OVERDUE';
            } elseif ($m->next_dose_at) {
                $status = 'next due '.Carbon::parse($m->next_dose_at)->format('H:i');
            } else {
                $status = 'no scheduled times today';
            }

            return "- {$m->name}: {$status}";
        })->implode("\n");

        $answer = "Today's meds for {$this->label($resident)}:\n".$lines;

        return [
            'intent' => 'todays_meds',
            'answer' => $answer,
            'data' => $withStatus->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'is_fully_administered_today' => (bool) $m->is_fully_administered_today,
                'is_overdue' => (bool) $m->is_overdue,
                'next_dose_at' => $m->next_dose_at,
            ])->values()->all(),
            'suggestions' => [],
        ];
    }

    private function answerPrn(Resident $resident): array
    {
        $prn = $resident->medicationOrders()
            ->where('is_active', true)
            ->get()
            ->filter(fn ($m) => stripos((string) $m->instructions, 'PRN') !== false
                || stripos((string) $m->instructions, 'as needed') !== false)
            ->values();

        if ($prn->isEmpty()) {
            return $this->reply('prn_status', "No PRN (as-needed) medications on record for {$this->label($resident)}.");
        }

        $data = $prn->map(function ($m) use ($resident) {
            $last = MedicationAdministration::where('medication_id', $m->id)
                ->where('resident_id', $resident->id)
                ->orderByDesc('administered_at')
                ->first();

            return [
                'id' => $m->id,
                'name' => $m->name,
                'last_given_at' => $last ? Carbon::parse($last->administered_at)->toIso8601String() : null,
            ];
        });

        $lines = $data->map(function ($row) {
            $when = $row['last_given_at']
                ? 'last given '.Carbon::parse($row['last_given_at'])->format('Y-m-d H:i')
                : 'no administration on record';

            return "- {$row['name']}: {$when}";
        })->implode("\n");

        $answer = "PRN meds for {$this->label($resident)}:\n".$lines
            ."\n\nNote: I show last-given only. I can't advise whether it's safe to give again.";

        return [
            'intent' => 'prn_status',
            'answer' => $answer,
            'data' => $data->all(),
            'suggestions' => [],
        ];
    }

    /**
     * @param  array<int, array{label: string, message: string}>  $suggestions
     * @return array{intent: string, answer: string, data: array, suggestions: array}
     */
    private function reply(string $intent, string $answer, array $data = [], array $suggestions = []): array
    {
        return ['intent' => $intent, 'answer' => $answer, 'data' => $data, 'suggestions' => $suggestions];
    }

    private function label(Resident $resident): string
    {
        return trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
    }

    /**
     * @return array<int, array{label: string, message: string}>
     */
    private function choiceSuggestions(Resident $resident): array
    {
        $name = $this->label($resident);

        return [
            ['label' => "Today's meds", 'message' => "today's meds for {$name}"],
            ['label' => 'PRN', 'message' => "PRN for {$name}"],
        ];
    }

    /**
     * Starter chips built from residents the user can see (FacilityScope active).
     *
     * @return array<int, array{label: string, message: string}>
     */
    public function starterSuggestions(User $user, int $limit = 3): array
    {
        $residents = Resident::query()->orderBy('first_name')->limit($limit)->get();

        $chips = [];
        foreach ($residents->values() as $i => $resident) {
            $name = $this->label($resident);
            if ($i % 2 === 0) {
                $chips[] = ['label' => "Today's meds for {$name}", 'message' => "today's meds for {$name}"];
            } else {
                $chips[] = ['label' => "PRN for {$name}", 'message' => "PRN for {$name}"];
            }
        }

        return $chips;
    }
}
