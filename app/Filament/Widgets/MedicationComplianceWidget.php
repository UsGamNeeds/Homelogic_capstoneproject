<?php

namespace App\Filament\Widgets;

use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MedicationComplianceWidget extends Widget
{
    protected static string $view = 'filament.widgets.medication-compliance-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * How far back the figures on this widget reach.
     */
    private const WINDOW_DAYS = 30;

    public function getViewData(): array
    {
        $since = Carbon::now()->subDays(self::WINDOW_DAYS);

        $totalScheduled = $this->administrations()->where('administered_at', '>=', $since)->count();
        $completed = $this->administrations()->where('administered_at', '>=', $since)
            ->where('status', 'completed')->count();
        $missed = $this->administrations()->where('administered_at', '>=', $since)
            ->where('status', 'missed')->count();

        $complianceRate = $totalScheduled > 0 ? round(($completed / $totalScheduled) * 100, 1) : 0;

        return [
            'compliance_rate' => $complianceRate,
            'total_scheduled' => $totalScheduled,
            'completed' => $completed,
            'missed' => $missed,
        ];
    }

    /**
     * Administrations this viewer is allowed to count.
     *
     * MedicationAdministration has no FacilityScope. Its only global scope is
     * `medicationExists`, and that is a raw subquery against the medications
     * table rather than the Eloquent model, so the facility rules never reach
     * it. Every other caller in the codebase arrives at this table through
     * medication ids that were already branch-filtered; this widget queries it
     * directly, so it has to carry the boundary itself.
     *
     * The rules below mirror App\Models\Scopes\FacilityScope deliberately, so
     * a number here means the same thing as a number anywhere else.
     */
    private function administrations(): Builder
    {
        $query = MedicationAdministration::query();
        $user = Auth::user();

        if ($user && $user->role === 'super_admin') {
            return $query;
        }

        try {
            $facility = app()->bound('facility') ? app('facility') : null;
        } catch (\Exception $e) {
            $facility = null;
        }

        $facilityId = $facility?->id ?? ($user?->facility_id ?: null);

        // No facility means no rows. FacilityScope returns unscoped here, which
        // is safe for models it is attached to because they are reached through
        // a scoped parent. This widget counts the table directly, so the same
        // choice would show one home every other home's month.
        if (! $facilityId) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereHas('branch', fn ($q) => $q->where('facility_id', $facilityId));

        // 'administrator' covers every branch in the facility; 'admin' is the
        // branch-level role and sees only their own.
        if ($user && $user->role === 'admin' && $user->assigned_branch_id) {
            $query->where('branch_id', $user->assigned_branch_id);
        }

        return $query;
    }
}
