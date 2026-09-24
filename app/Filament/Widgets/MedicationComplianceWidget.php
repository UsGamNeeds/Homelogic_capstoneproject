<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ResolvesViewerScope;
use App\Models\MedicationAdministration;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class MedicationComplianceWidget extends Widget
{
    use ResolvesViewerScope;

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
     */
    private function administrations(): Builder
    {
        $query = MedicationAdministration::query();

        if ($this->viewerIsSuperAdmin()) {
            return $query;
        }

        $branchIds = $this->viewerBranchIds();

        // No facility means no rows. FacilityScope returns unscoped here, which
        // is safe for models it is attached to because they are reached through
        // a scoped parent. This widget counts the table directly, so the same
        // choice would show one home every other home's month.
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }
}
