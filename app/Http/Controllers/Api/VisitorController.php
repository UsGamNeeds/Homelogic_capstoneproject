<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VisitorController extends Controller
{
    /**
     * Check in a visitor
     */
    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'visit_purpose' => 'required|string|max:255',
            'visiting_resident_id' => 'nullable|exists:residents,id',
            'visiting_staff_id' => 'nullable|exists:users,id',
            'expected_duration_minutes' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Get facility_id from branch
        $branch = \App\Models\Branch::findOrFail($validated['branch_id']);

        $visitor = Visitor::create([
            'branch_id' => $validated['branch_id'],
            'facility_id' => $branch->facility_id ?? null,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'visit_purpose' => $validated['visit_purpose'],
            'visiting_resident_id' => $validated['visiting_resident_id'] ?? null,
            'visiting_staff_id' => $validated['visiting_staff_id'] ?? null,
            'check_in_at' => now(),
            'expected_duration_minutes' => $validated['expected_duration_minutes'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
            'checked_in_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Visitor checked in successfully',
            'visitor' => $visitor->load(['branch', 'visitingResident', 'visitingStaff', 'checkedInBy']),
        ], 201);
    }

    /**
     * Check out a visitor
     */
    public function checkOut(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $visitor = Visitor::findOrFail($id);

        if (!$visitor->is_active) {
            return response()->json([
                'message' => 'Visitor is already checked out',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $visitor->checkOut($user, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Visitor checked out successfully',
            'visitor' => $visitor->fresh()->load(['branch', 'visitingResident', 'visitingStaff', 'checkedInBy', 'checkedOutBy']),
        ]);
    }

    /**
     * List visitors
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Visitor::with(['branch', 'visitingResident', 'visitingStaff', 'checkedInBy', 'checkedOutBy']);

        // Apply branch filter for caregivers
        if ($user->isCaregiver() && $user->assigned_branch_id) {
            $query->where('branch_id', $user->assigned_branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by visiting resident
        if ($request->filled('visiting_resident_id')) {
            $query->where('visiting_resident_id', $request->get('visiting_resident_id'));
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('check_in_at', '>=', $request->get('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('check_in_at', '<=', $request->get('end_date'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $visitors = $query->orderBy('check_in_at', 'desc')->paginate($perPage);

        return response()->json($visitors);
    }

    /**
     * Get active visitors
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $query = Visitor::with(['branch', 'visitingResident', 'visitingStaff', 'checkedInBy'])
            ->where('is_active', true);

        // Apply branch filter for caregivers
        if ($user->isCaregiver() && $user->assigned_branch_id) {
            $query->where('branch_id', $user->assigned_branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $visitors = $query->orderBy('check_in_at', 'asc')->paginate($perPage);

        return response()->json($visitors);
    }

    /**
     * Get visitor details
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $visitor = Visitor::with([
            'branch',
            'facility',
            'visitingResident',
            'visitingStaff',
            'checkedInBy',
            'checkedOutBy'
        ])->findOrFail($id);

        return response()->json($visitor);
    }
}

