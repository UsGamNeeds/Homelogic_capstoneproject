<?php

namespace App\Http\Controllers\Api;

use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\MedicationDelivery;
use App\Models\Resident;
use App\Services\MedicationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MedicationDashboardController extends BaseApiController
{
    protected $medicationService;

    public function __construct(MedicationService $medicationService)
    {
        $this->medicationService = $medicationService;
    }

    /**
     * Every aggregate this endpoint knows how to build, in payload order.
     */
    private const SECTIONS = [
        'today',
        'upcoming',
        'missed_today',
        'adherence_trend',
        'recent_activity',
        'resident_summary',
        'delivery_status',
    ];

    /**
     * The aggregates that read the active-medication set, and so pay for it.
     */
    private const SECTIONS_NEEDING_ACTIVE_MEDICATIONS = ['today', 'upcoming', 'resident_summary'];

    public function index(Request $request): JsonResponse
    {
        // Outside the try below: a validation failure is a 422 for the caller,
        // not a 500 to be logged as a dashboard error.
        $sections = $this->requestedSections($request);

        try {
            $user = $request->user();
            $now = Carbon::now(config('app.timezone'));
            $today = $now->toDateString();

            $isCaregiver = $this->isCaregiver($user);
            $branchIds = $this->resolveBranchIds($user, $isCaregiver);

            if (empty($branchIds)) {
                return response()->json($this->only($sections, [
                    'today' => fn () => ['scheduled' => 0, 'administered' => 0, 'missed' => 0, 'refused' => 0, 'adherence' => 0, 'active_medications' => 0],
                    'upcoming' => fn () => [],
                    'missed_today' => fn () => [],
                    'adherence_trend' => fn () => [],
                    'recent_activity' => fn () => [],
                    'resident_summary' => fn () => [],
                    'delivery_status' => fn () => ['today_count' => 0, 'pending_verification' => 0, 'recent' => []],
                ]));
            }

            // Loaded once, shared by the three aggregates that need it, and
            // skipped entirely when none of them was asked for.
            $activeMeds = ! empty(array_intersect($sections, self::SECTIONS_NEEDING_ACTIVE_MEDICATIONS))
                ? $this->getActiveMedications($branchIds, $now)
                : collect();

            return response()->json($this->only($sections, [
                'today' => fn () => $this->getTodayStats($today, $activeMeds),
                'upcoming' => fn () => $this->getUpcomingMedications($activeMeds, $now),
                'missed_today' => fn () => $this->getMissedToday($branchIds, $today),
                'adherence_trend' => fn () => $this->getAdherenceTrend($branchIds, $now),
                'recent_activity' => fn () => $this->getRecentActivity($branchIds),
                'resident_summary' => fn () => $this->getResidentSummary($branchIds, $today, $activeMeds),
                'delivery_status' => fn () => $this->getDeliveryStatus($branchIds, $today),
            ]));
        } catch (\Exception $e) {
            Log::error('Medication dashboard error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load medication dashboard.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Which aggregates the caller asked for.
     *
     * A caller that wants four numbers should not pay for six payloads it
     * discards. Absent `only`, the full set is returned, so existing callers
     * see no change. An unrecognised name is rejected rather than ignored: a
     * typo that silently returned everything would look like the parameter
     * working while the request stayed as slow as it ever was.
     *
     * @return array<int, string>
     */
    private function requestedSections(Request $request): array
    {
        $request->validate([
            'only' => [
                'sometimes',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    // Checked here rather than leaning on a 'string' rule: Laravel
                    // runs every rule for an attribute, so `?only[]=today` would
                    // still reach this closure, and an array landing in
                    // parseSections() raises a TypeError, which is an Error and
                    // not caught anywhere in this controller.
                    if (! is_string($value)) {
                        $fail('The only field must be a comma separated list of section names.');

                        return;
                    }

                    $requested = $this->parseSections($value);

                    if (empty($requested)) {
                        $fail('The only field must name at least one section. Available: '.implode(', ', self::SECTIONS).'.');

                        return;
                    }

                    $unknown = array_diff($requested, self::SECTIONS);

                    if (! empty($unknown)) {
                        $fail('Unknown section(s): '.implode(', ', $unknown).'. Available: '.implode(', ', self::SECTIONS).'.');
                    }
                },
            ],
        ]);

        if (! $request->filled('only')) {
            return self::SECTIONS;
        }

        // input(), not query(): a GET carrying a JSON body is validated out of
        // the JSON bag, and reading a different bag here would hand null to
        // parseSections() and raise a TypeError no catch in this class sees.
        return $this->parseSections($request->input('only'));
    }

    /**
     * @return array<int, string>
     */
    private function parseSections(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($s) => $s !== ''));
    }

    /**
     * Run only the builders that were asked for, keeping the payload's order.
     *
     * @param  array<int, string>  $sections
     * @param  array<string, callable>  $builders
     * @return array<string, mixed>
     */
    private function only(array $sections, array $builders): array
    {
        $payload = [];

        foreach ($builders as $key => $build) {
            if (in_array($key, $sections, true)) {
                $payload[$key] = $build();
            }
        }

        return $payload;
    }

    private function resolveBranchIds($user, bool $isCaregiver): array
    {
        if ($isCaregiver && $user->assigned_branch_id) {
            return [$user->assigned_branch_id];
        }

        if ($user->facility_id) {
            return $this->getFacilityBranchIds($user->facility_id);
        }

        return [];
    }

    /**
     * The medications in scope today, with the administrations that can speak
     * to them already loaded.
     *
     * The eager load is the point. Reading `$med->administrations` inside a
     * per-medication loop used to fire one query per medication and pull that
     * medication's entire history back, so the endpoint slowed down both with
     * the size of the home and with every week the home kept using it.
     *
     * The window runs an hour past midnight at each end, because a dose binds
     * to a slot within MedicationService::SLOT_MATCH_MINUTES of it and that
     * reaches across midnight in both directions. It must not be narrowed
     * below that constant: a dose the service would have matched but this
     * query did not load reads as not given, which is a wrong compliance
     * number rather than a slow one.
     */
    private function getActiveMedications(array $branchIds, Carbon $now)
    {
        $today = $now->toDateString();
        $from = $now->copy()->startOfDay()->subMinutes(MedicationService::SLOT_MATCH_MINUTES);
        $to = $now->copy()->endOfDay()->addMinutes(MedicationService::SLOT_MATCH_MINUTES);

        return Medication::with([
            'resident:id,first_name,last_name',
            'drug:id,name',
            'administrations' => fn ($q) => $q->whereBetween('administered_at', [$from, $to]),
        ])
            ->where('is_active', true)
            ->whereIn('branch_id', $branchIds)
            ->where('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->get();
    }

    /**
     * The four counts the compliance figure is built from.
     *
     * This reads `time_1..4` and the day's administrations, both of which are
     * already on the model. It used to route through
     * MedicationService::getMedicationsWithStatus first, which computes next
     * dose, overdue and per-slot state for every medication and then had all
     * of it thrown away here.
     */
    private function getTodayStats(string $today, $activeMeds): array
    {
        $scheduled = 0;
        $administered = 0;
        $missed = 0;
        $refused = 0;

        foreach ($activeMeds as $med) {
            // Count total slots across all meds
            for ($i = 1; $i <= 4; $i++) {
                if ($med->{"time_{$i}"}) {
                    $scheduled++;
                }
            }

            // Count completed/missed/refused from administrations
            foreach ($med->administrations as $admin) {
                if ($admin->administered_at && Carbon::parse($admin->administered_at)->toDateString() === $today) {
                    if ($admin->status === 'completed') {
                        $administered++;
                    } elseif ($admin->status === 'missed') {
                        $missed++;
                    } elseif ($admin->status === 'refused') {
                        $refused++;
                    }
                }
            }
        }

        $adherence = $scheduled > 0 ? round(($administered / $scheduled) * 100) : 0;

        return [
            'scheduled' => $scheduled,
            'administered' => $administered,
            'missed' => $missed,
            'refused' => $refused,
            'adherence' => min($adherence, 100),
            'active_medications' => $activeMeds->count(),
        ];
    }

    private function getUpcomingMedications($activeMeds, Carbon $now): array
    {
        $timezone = config('app.timezone');
        $cutoff = $now->copy()->addHours(4);

        // Use MedicationService to find soonest doses
        $medsWithStatus = $this->medicationService->getMedicationsWithStatus($activeMeds, $now->toDateString());

        $upcoming = [];
        foreach ($medsWithStatus as $med) {
            // MedicationService provides next_dose_at which is pre-calculated/validated
            if ($med->next_dose_at) {
                $nextDose = Carbon::parse($med->next_dose_at)->setTimezone($timezone);

                // Only include if it's within the 4-hour window
                if ($nextDose->gt($now) && $nextDose->lte($cutoff)) {
                    $upcoming[] = [
                        'medication_id' => $med->id,
                        'medication_name' => $med->name ?: $med->drug?->name ?? 'Unknown',
                        'resident_id' => $med->resident_id,
                        'resident_name' => $med->resident
                            ? trim($med->resident->first_name.' '.$med->resident->last_name)
                            : 'Unknown',
                        'scheduled_time' => $nextDose->format('g:i A'),
                        'scheduled_at' => $nextDose->toIso8601String(),
                        'instructions' => $med->instructions,
                        'minutes_until' => (int) $now->diffInMinutes($nextDose, false),
                    ];
                }
            }
        }

        usort($upcoming, fn ($a, $b) => $a['minutes_until'] - $b['minutes_until']);

        return array_slice($upcoming, 0, 15);
    }

    private function getMissedToday(array $branchIds, string $today): array
    {
        return MedicationAdministration::with([
            'medication:id,name,drug_id,instructions',
            'medication.drug:id,name',
            'resident:id,first_name,last_name',
        ])
            ->whereIn('branch_id', $branchIds)
            ->whereDate('administered_at', $today)
            ->where('status', 'missed')
            ->orderBy('administered_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'medication_name' => $a->medication?->name ?: $a->medication?->drug?->name ?? 'Unknown',
                'resident_id' => $a->resident_id,
                'resident_name' => $a->resident
                    ? trim($a->resident->first_name.' '.$a->resident->last_name)
                    : 'Unknown',
                'scheduled_time' => $a->administered_at
                    ? Carbon::parse($a->administered_at)->setTimezone(config('app.timezone'))->format('g:i A')
                    : null,
                'instructions' => $a->medication?->instructions,
            ])
            ->toArray();
    }

    /**
     * Seven days of adherence, from two queries instead of fifteen.
     *
     * The shape of this is unchanged; the cost is not. It used to re-query the
     * active medications once per day and rebuild every medication's full
     * status to read one thing off the result: whether each of `time_1..4` was
     * set. Those are plain columns, and a medication's schedule does not change
     * between the days of the week being reported, so the set is fetched once
     * for the whole window and each day asks which of them it covers.
     */
    private function getAdherenceTrend(array $branchIds, Carbon $now): array
    {
        $dateFrom = $now->copy()->subDays(6)->startOfDay();
        $dateTo = $now->copy()->endOfDay();

        $dailyData = MedicationAdministration::whereIn('branch_id', $branchIds)
            ->whereBetween('administered_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(administered_at) as date, status, count(*) as count')
            ->groupBy('date', 'status')
            ->get();

        $medsInWindow = Medication::where('is_active', true)
            ->whereIn('branch_id', $branchIds)
            ->where('start_date', '<=', $dateTo->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $dateFrom->toDateString()))
            ->get(['id', 'start_date', 'end_date', 'time_1', 'time_2', 'time_3', 'time_4']);

        $trend = [];
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = $now->copy()->subDays($daysAgo);
            $dateStr = $date->toDateString();

            $scheduled = 0;
            foreach ($medsInWindow as $med) {
                if (! $this->wasScheduledOn($med, $dateStr)) {
                    continue;
                }

                for ($i = 1; $i <= 4; $i++) {
                    if ($med->{"time_{$i}"}) {
                        $scheduled++;
                    }
                }
            }

            $dayData = $dailyData->where('date', $dateStr);
            $administered = (int) $dayData->where('status', 'completed')->sum('count');
            $missed = (int) $dayData->where('status', 'missed')->sum('count');
            $refused = (int) $dayData->where('status', 'refused')->sum('count');

            $adherence = $scheduled > 0 ? round(($administered / $scheduled) * 100) : 0;

            $trend[] = [
                'date' => $dateStr,
                'day' => $date->format('D'),
                'scheduled' => $scheduled,
                'administered' => $administered,
                'missed' => $missed,
                'refused' => $refused,
                'adherence' => min($adherence, 100),
            ];
        }

        return $trend;
    }

    /**
     * Whether a medication was running on a given day.
     *
     * This mirrors the query it replaces exactly, including its treatment of a
     * missing start date: `where('start_date', '<=', $day)` never matches null,
     * so a medication without one was left out of the count, and still is.
     */
    private function wasScheduledOn(Medication $med, string $dateStr): bool
    {
        if (! $med->start_date) {
            return false;
        }

        if (Carbon::parse($med->start_date)->toDateString() > $dateStr) {
            return false;
        }

        if ($med->end_date && Carbon::parse($med->end_date)->toDateString() < $dateStr) {
            return false;
        }

        return true;
    }

    private function getRecentActivity(array $branchIds): array
    {
        return MedicationAdministration::with([
            'medication:id,name,drug_id',
            'medication.drug:id,name',
            'resident:id,first_name,last_name',
            'administeredBy:id,first_name,last_name,name',
        ])
            ->whereIn('branch_id', $branchIds)
            ->orderBy('administered_at', 'desc')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'medication_name' => $a->medication?->name ?: $a->medication?->drug?->name ?? 'Unknown',
                'resident_name' => $a->resident
                    ? trim($a->resident->first_name.' '.$a->resident->last_name)
                    : 'Unknown',
                'administered_by' => $a->administeredBy?->name
                    ?: trim(($a->administeredBy?->first_name ?? '').' '.($a->administeredBy?->last_name ?? ''))
                    ?: 'System',
                'status' => $a->status,
                'administered_at' => $a->administered_at,
                'dosage_given' => $a->dosage_given,
                'notes' => $a->notes,
            ])
            ->toArray();
    }

    private function getResidentSummary(array $branchIds, string $today, $activeMeds): array
    {
        $medsByResident = $activeMeds->groupBy('resident_id');

        if ($medsByResident->isEmpty()) {
            return [];
        }

        $residentIds = $medsByResident->keys()->toArray();

        $residents = Resident::whereIn('id', $residentIds)
            ->where('status', 'active')
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->keyBy('id');

        // Use MedicationService for uniform status calculation per resident
        $summary = [];
        foreach ($medsByResident as $residentId => $meds) {
            $resident = $residents->get($residentId);
            if (! $resident) {
                continue;
            }

            $medsWithStatus = $this->medicationService->getMedicationsWithStatus($meds, $today);

            $scheduled = 0;
            $administered = 0;
            $missedToday = 0;

            foreach ($medsWithStatus as $med) {
                // Count slots
                for ($i = 1; $i <= 4; $i++) {
                    if ($med->{"time_{$i}"}) {
                        $scheduled++;
                    }
                }

                // Count admins from the loaded administrations
                foreach ($med->administrations as $admin) {
                    if ($admin->administered_at && Carbon::parse($admin->administered_at)->toDateString() === $today) {
                        if ($admin->status === 'completed') {
                            $administered++;
                        } elseif ($admin->status === 'missed') {
                            $missedToday++;
                        }
                    }
                }
            }

            $adherence = $scheduled > 0 ? round(($administered / $scheduled) * 100) : 0;

            $summary[] = [
                'resident_id' => $residentId,
                'resident_name' => trim($resident->first_name.' '.$resident->last_name),
                'active_medications' => $meds->count(),
                'scheduled_today' => $scheduled,
                'administered_today' => $administered,
                'missed_today' => $missedToday,
                'adherence' => min($adherence, 100),
            ];
        }

        usort($summary, function ($a, $b) {
            if ($b['missed_today'] !== $a['missed_today']) {
                return $b['missed_today'] - $a['missed_today'];
            }

            return $a['adherence'] - $b['adherence'];
        });

        return $summary;
    }

    private function getDeliveryStatus(array $branchIds, string $today): array
    {
        $todayCount = MedicationDelivery::whereIn('branch_id', $branchIds)
            ->where('received_date', $today)
            ->count();

        $pendingVerification = MedicationDelivery::whereIn('branch_id', $branchIds)
            ->where('status', 'received')
            ->count();

        $recentDeliveries = MedicationDelivery::with([
            'resident:id,first_name,last_name',
            'receivedBy:id,first_name,last_name,name',
        ])
            ->whereIn('branch_id', $branchIds)
            ->orderBy('received_date', 'desc')
            ->orderBy('received_time', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'pharmacy_name' => $d->pharmacy_name,
                'resident_name' => $d->resident
                    ? trim($d->resident->first_name.' '.$d->resident->last_name)
                    : 'Batch Delivery',
                'quantity' => $d->quantity_received,
                'status' => $d->status,
                'delivery_type' => $d->delivery_type,
                'received_date' => $d->received_date,
                'received_by' => $d->receivedBy?->name
                    ?: trim(($d->receivedBy?->first_name ?? '').' '.($d->receivedBy?->last_name ?? ''))
                    ?: 'Unknown',
            ])
            ->toArray();

        return [
            'today_count' => $todayCount,
            'pending_verification' => $pendingVerification,
            'recent' => $recentDeliveries,
        ];
    }
}
