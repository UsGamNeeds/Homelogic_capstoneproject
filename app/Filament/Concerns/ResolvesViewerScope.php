<?php

namespace App\Filament\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * How far a dashboard widget may look.
 *
 * Widgets sit outside the request pipeline that most of the app relies on for
 * tenancy, and they hit two gaps in it. FacilityScope is opt-in per model, so
 * a widget querying a model that never opted in reads the whole platform. And
 * a widget that caches its answer under a fixed key hands that answer to the
 * next facility that asks, however well scoped the query underneath it was.
 *
 * The rules here mirror App\Models\Scopes\FacilityScope deliberately, so a
 * number on a dashboard means what a number means anywhere else.
 */
trait ResolvesViewerScope
{
    protected function viewerIsSuperAdmin(): bool
    {
        return Auth::user()?->role === 'super_admin';
    }

    protected function viewerFacilityId(): ?int
    {
        try {
            $facility = app()->bound('facility') ? app('facility') : null;
        } catch (\Exception $e) {
            $facility = null;
        }

        return $facility?->id ?? (Auth::user()?->facility_id ?: null);
    }

    /**
     * The branch a viewer is confined to, if any.
     *
     * 'administrator' covers every branch in the facility; 'admin' is the
     * branch-level role and sees only its own.
     */
    protected function viewerBranchId(): ?int
    {
        $user = Auth::user();

        return ($user && $user->role === 'admin' && $user->assigned_branch_id)
            ? (int) $user->assigned_branch_id
            : null;
    }

    /**
     * A cache key nobody with a different reach can be served.
     *
     * The signature has to carry the branch as well as the facility: a branch
     * admin and a facility administrator in the same home legitimately see
     * different numbers, and sharing a key would hand one the other's.
     */
    protected function scopedCacheKey(string $key): string
    {
        return $key.':'.$this->viewerScopeSignature();
    }

    protected function viewerScopeSignature(): string
    {
        if ($this->viewerIsSuperAdmin()) {
            return 'all';
        }

        $facilityId = $this->viewerFacilityId();

        // FacilityScope returns unscoped when it cannot resolve a facility, so
        // whatever this viewer sees is platform-wide. It gets its own bucket
        // rather than poisoning a real facility's.
        if (! $facilityId) {
            return 'unresolved';
        }

        $branchId = $this->viewerBranchId();

        return $branchId
            ? 'facility-'.$facilityId.':branch-'.$branchId
            : 'facility-'.$facilityId;
    }

    /**
     * Staff this viewer may count.
     *
     * FacilityScope exempts User permanently (FacilityScope.php:39-41, to avoid
     * recursion while authenticating), so every User query in a widget is
     * platform-wide unless it says otherwise. Tenant-keying a cache around one
     * does not help: it just gives each facility its own copy of the same
     * global number.
     */
    protected function viewerStaff(): Builder
    {
        $query = User::query();

        if ($this->viewerIsSuperAdmin()) {
            return $query;
        }

        $facilityId = $this->viewerFacilityId();

        if (! $facilityId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('facility_id', $facilityId);

        if ($branchId = $this->viewerBranchId()) {
            $query->where('assigned_branch_id', $branchId);
        }

        return $query;
    }

    /**
     * Branch ids for filtering tables reached outside Eloquent.
     *
     * Null means no restriction, for a super admin. An empty array means no
     * rows, which is the honest answer for a viewer whose facility will not
     * resolve.
     *
     * @return array<int, int>|null
     */
    protected function viewerBranchIds(): ?array
    {
        if ($this->viewerIsSuperAdmin()) {
            return null;
        }

        $facilityId = $this->viewerFacilityId();

        if (! $facilityId) {
            return [];
        }

        if ($branchId = $this->viewerBranchId()) {
            return [$branchId];
        }

        return Branch::withoutGlobalScopes()
            ->where('facility_id', $facilityId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
