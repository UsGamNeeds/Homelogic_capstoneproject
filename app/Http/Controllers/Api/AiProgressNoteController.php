<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgressNoteDraft;
use App\Models\Resident;
use App\Models\TLog;
use App\Services\AiAssistant\ProgressNoteAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A separate module from T-Logs (the real progress-note feature) end to end:
 * its own table (progress_note_drafts), its own routes, its own React tab.
 * A draft only ever becomes a real record if approve() is called, and that
 * creates an ordinary TLog row via TLog::create() — TLogController and the
 * t_logs schema are never touched by any of this.
 */
class AiProgressNoteController extends Controller
{
    public function __construct(private ProgressNoteAgentService $agent)
    {
    }

    public function index(Request $request, Resident $resident): JsonResponse
    {
        $this->authorizeResidentAccess($resident);

        $drafts = ProgressNoteDraft::where('resident_id', $resident->id)
            ->latest('id')
            ->limit((int) $request->input('limit', 20))
            ->get();

        return response()->json(['resident_id' => $resident->id, 'drafts' => $drafts]);
    }

    public function store(Request $request, Resident $resident): JsonResponse
    {
        $this->authorizeResidentAccess($resident);

        $validated = $request->validate([
            'shift_entry' => ['nullable', 'string', 'max:8000'],
        ]);

        $user = $request->user();
        $result = $this->agent->draft($resident, $validated['shift_entry'] ?? null, $user);

        $draft = ProgressNoteDraft::create([
            'resident_id' => $resident->id,
            'branch_id' => $resident->branch_id,
            'facility_id' => $resident->branch?->facility_id,
            'created_by' => $user->id,
            'raw_shift_entry' => $validated['shift_entry'] ?? null,
            'window_label' => $result['summary']['window_label'] ?? 'last 7 days',
            'draft_json' => $result['draft'],
            'summary_json' => $result['summary'],
            'medication_check_json' => $result['medication_check'],
            'mode' => $result['mode'],
            'model' => $result['model'],
            'status' => $result['status'],
        ]);

        return response()->json(['draft' => $draft, 'label' => $result['label']], 201);
    }

    /**
     * Approve a draft: requires an explicit `confirmed: true` from the
     * reviewer (never defaults to true) and creates a real t_logs row. This
     * is the ONLY code path in this module that writes to t_logs, and it
     * does so through the unmodified TLog model exactly as TLogController
     * would.
     */
    public function approve(Request $request, Resident $resident, ProgressNoteDraft $draft): JsonResponse
    {
        $this->authorizeReviewer($resident);
        $this->ensureDraftBelongsToResident($resident, $draft);

        $validated = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'narrative' => ['nullable', 'string', 'max:8000'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($draft->status === ProgressNoteDraft::STATUS_APPROVED) {
            return response()->json(['message' => 'Draft already approved.'], 409);
        }

        $reviewer = $request->user();
        $finalNarrative = trim((string) ($validated['narrative'] ?? ($draft->draft_json['narrative'] ?? '')));

        $tLog = DB::transaction(function () use ($resident, $draft, $reviewer, $finalNarrative, $validated) {
            $tLog = TLog::create([
                'resident_id' => $resident->id,
                'branch_id' => $resident->branch_id,
                'types' => ['notes'],
                'notification_level' => 'low',
                'summary' => 'AI-assisted progress note (reviewed)',
                'description' => $finalNarrative,
                'reporter_id' => $reviewer->id,
                'reported_on' => now(),
                'entered_by_id' => $reviewer->id,
            ]);

            $draft->update([
                'status' => ProgressNoteDraft::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
                't_log_id' => $tLog->id,
            ]);

            return $tLog;
        });

        return response()->json(['draft' => $draft->fresh(), 't_log' => $tLog]);
    }

    public function reject(Request $request, Resident $resident, ProgressNoteDraft $draft): JsonResponse
    {
        $this->authorizeReviewer($resident);
        $this->ensureDraftBelongsToResident($resident, $draft);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);

        $draft->update([
            'status' => ProgressNoteDraft::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return response()->json(['draft' => $draft->fresh()]);
    }

    /** Facility-wide manager queue: every flagged draft awaiting review, across residents. No notifications are sent for these — a manager finds them by opening this queue. */
    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['administrator', 'super_admin'], true)) {
            $this->forbidden('Manager review access is required.');
        }

        $drafts = ProgressNoteDraft::where('status', ProgressNoteDraft::STATUS_FLAGGED)
            ->with('resident:id,first_name,last_name,branch_id')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json($drafts);
    }

    private function ensureDraftBelongsToResident(Resident $resident, ProgressNoteDraft $draft): void
    {
        if ($draft->resident_id !== $resident->id) {
            $this->notFound('Draft not found for this resident.');
        }
    }

    /**
     * abort() with a Response instance (not abort(403, 'string')) deliberately:
     * it throws HttpResponseException, which Laravel's exception handler
     * special-cases and returns as-is before mapException()/prepareException()
     * or any registered render() callback ever run. A plain abort(403, ...)
     * or a thrown AuthorizationException both end up converted internally to
     * AccessDeniedHttpException before this app's custom renderers see them,
     * and that falls through to Laravel's default view-based error page —
     * which fails in this app's test/API environment (no `view` binding),
     * turning an intended 403 into a fatal 500. Passing a Response sidesteps
     * that pipeline entirely.
     */
    private function forbidden(string $message): never
    {
        abort(response()->json(['message' => $message], 403));
    }

    private function notFound(string $message): never
    {
        abort(response()->json(['message' => $message], 404));
    }

    private function authorizeResidentAccess(Resident $resident): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->forbidden('Access is not permitted.');
        }

        if ($user->role === 'super_admin') {
            return;
        }

        $facilityId = $resident->branch?->facility_id;
        if (! $facilityId || $facilityId !== $user->facility_id) {
            $this->notFound('Resident not found.');
        }
    }

    /** Approving/rejecting a draft is a review action, not a charting action — gated to admin-level roles. */
    private function authorizeReviewer(Resident $resident): void
    {
        $this->authorizeResidentAccess($resident);

        $user = Auth::user();
        if (! in_array($user->role, ['administrator', 'super_admin'], true)) {
            $this->forbidden('Reviewing AI-drafted notes requires an administrator role.');
        }
    }
}
