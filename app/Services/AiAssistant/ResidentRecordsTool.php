<?php

namespace App\Services\AiAssistant;

use App\Models\BehaviorChart;
use App\Models\Resident;
use Illuminate\Support\Carbon;

/**
 * The "resident_records" tool: queries the database directly for a fixed
 * trailing window, rather than trusting client-supplied JSON the way the
 * older ChartAssistantController payload does. The agent (or the fixed-
 * sequence fallback) calls this once per draft; the result is the single
 * source of truth for every fact that ends up in the note.
 */
class ResidentRecordsTool
{
    public function __construct(private int $windowDays = 7)
    {
    }

    public function fetch(Resident $resident): array
    {
        $since = Carbon::now()->subDays($this->windowDays);

        $vitals = $resident->vitalSigns()
            ->where('measurement_date', '>=', $since->toDateString())
            ->orderByDesc('measurement_date')
            ->get();

        $sleep = $resident->sleepRecords()
            ->where('sleep_date', '>=', $since->toDateString())
            ->get();

        $behaviorCharts = BehaviorChart::where('resident_id', $resident->id)
            ->where('chart_date', '>=', $since->toDateString())
            ->with('logs')
            ->get();

        $behaviorEvents = $behaviorCharts->flatMap(fn ($chart) => $chart->logs)->values();

        $appointments = $resident->appointments()
            ->where('appointment_date', '>=', $since->toDateString())
            ->get();

        $activeMedications = $resident->medicationOrders()->where('is_active', true)->get();

        $administrations = $resident->medicationAdministrations()
            ->where('administered_at', '>=', $since)
            ->with('medication')
            ->orderByDesc('administered_at')
            ->get();

        return [
            'window_days' => $this->windowDays,
            'window_start' => $since->toDateString(),
            'vitals' => [
                'count' => $vitals->count(),
                'critical_count' => $vitals->where('status', 'critical')->count(),
                'warning_count' => $vitals->where('status', 'warning')->count(),
                'latest' => $vitals->first()?->only([
                    'measurement_date', 'temperature', 'pulse', 'oxygen_saturation',
                    'systolic', 'diastolic', 'status',
                ]),
            ],
            'sleep' => [
                'count' => $sleep->count(),
                'average_hours' => round((float) $sleep->avg('total_sleep_hours'), 1),
                'average_quality' => round((float) $sleep->avg('sleep_quality'), 1),
            ],
            'behavior_events' => $behaviorEvents->map(fn ($log) => [
                'occurred_at' => optional($log->occurred_at)->toDateTimeString(),
                'description' => $log->behavior_description,
                'provider_notified' => (bool) $log->reported_to_provider,
                'outcome' => $log->outcome,
            ])->values()->all(),
            'appointments' => [
                'upcoming_count' => $appointments->where('appointment_date', '>=', Carbon::now()->toDateString())->count(),
                'items' => $appointments->map(fn ($a) => [
                    'date' => optional($a->appointment_date)->toDateString(),
                    'title' => $a->title,
                    'status' => $a->status,
                ])->values()->all(),
            ],
            'medications' => [
                'active' => $activeMedications->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'instructions' => $m->instruction_display ?? $m->instructions,
                ])->values()->all(),
                'administrations' => $administrations->map(fn ($a) => [
                    'medication_id' => $a->medication_id,
                    'medication_name' => $a->medication?->name,
                    'administered_at' => optional($a->administered_at)->toDateTimeString(),
                    'status' => $a->status,
                ])->values()->all(),
                'missed_count' => $administrations->whereIn('status', ['missed', 'refused'])->count(),
            ],
        ];
    }
}
