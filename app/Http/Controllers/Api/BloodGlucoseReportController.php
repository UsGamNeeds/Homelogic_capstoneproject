<?php

namespace App\Http\Controllers\Api;

use App\Models\BloodGlucoseReport;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BloodGlucoseReportController extends BaseApiController
{
    private const VALIDATION_RULES = [
        'resident_id' => 'required|exists:residents,id',
        'reported_by' => 'nullable|exists:users,id',
        'report_date' => 'required|date',
        'notification_level' => 'nullable|in:low,medium,high,urgent',
        'reading_date' => 'required|date',
        'reading_time' => 'nullable|date_format:H:i',
        'value' => 'required|numeric|min:0|max:1000',
        'method_used' => 'nullable|in:fingerstick,cgm,lab_draw',
        'fasting' => 'nullable|boolean',
        'hours_since_last_meal' => 'nullable|numeric|min:0|max:48',
        'insulin_given' => 'nullable|boolean',
        'nurse_notified' => 'nullable|boolean',
        'treatment_type' => 'nullable|in:none,insulin,oral_medication,dietary,other',
        'medication_name' => 'nullable|string|max:255',
        'medication_amount' => 'nullable|string|max:100',
        'medication_route' => 'nullable|string|max:100',
        'medication_strength' => 'nullable|string|max:100',
        'medication_frequency' => 'nullable|string|max:100',
        'comments' => 'nullable|string|max:5000',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = BloodGlucoseReport::with(['resident', 'branch', 'reportedByUser']);

        $this->applyBranchFilter($query, $request);

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->get('resident_id'));
        }
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('reading_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('reading_date', '<=', $request->get('date_to'));
        }

        $reports = $query->orderBy('reading_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(self::VALIDATION_RULES);

        $resident = Resident::findOrFail($validated['resident_id']);
        if (! $resident->branch_id) {
            return $this->error('Resident must be assigned to a branch.', 422);
        }

        $validated['branch_id'] = $resident->branch_id;
        $validated['reported_by'] = $validated['reported_by'] ?? auth()->id();

        $report = BloodGlucoseReport::create($validated);

        return response()->json($report->load(['resident', 'branch', 'reportedByUser']), 201);
    }

    public function show($id): JsonResponse
    {
        $report = BloodGlucoseReport::with(['resident', 'branch', 'reportedByUser'])->findOrFail($id);

        if (! $this->checkBranchAccess($report)) {
            return $this->error('You do not have access to this report.', 403);
        }

        return response()->json($report);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $report = BloodGlucoseReport::findOrFail($id);

        if (! $this->checkBranchAccess($report)) {
            return $this->error('You do not have access to this report.', 403);
        }

        $rules = self::VALIDATION_RULES;
        $rules['resident_id'] = 'sometimes|exists:residents,id';
        $rules['report_date'] = 'sometimes|date';
        $rules['reading_date'] = 'sometimes|date';
        $rules['value'] = 'sometimes|numeric|min:0|max:1000';

        $validated = $request->validate($rules);

        if (isset($validated['resident_id']) && $validated['resident_id'] != $report->resident_id) {
            $resident = Resident::findOrFail($validated['resident_id']);
            if (! $resident->branch_id) {
                return $this->error('Resident must be assigned to a branch.', 422);
            }
            $validated['branch_id'] = $resident->branch_id;
        }

        $report->update($validated);

        return response()->json($report->load(['resident', 'branch', 'reportedByUser']));
    }

    public function destroy($id): JsonResponse
    {
        $report = BloodGlucoseReport::findOrFail($id);

        if (! $this->checkBranchAccess($report)) {
            return $this->error('You do not have access to this report.', 403);
        }

        $report->delete();

        return response()->json(['message' => 'Blood glucose report deleted successfully']);
    }
}
