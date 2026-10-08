<?php

namespace App\Http\Controllers\Api;

use App\Models\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared, growable lookup for appointment specialties. Any authenticated user can
 * search the existing list or add a new one — same "select existing or add new"
 * pattern as HealthcareProviderController (consultants) and HospitalController.
 */
class SpecialtyController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Specialty::query()->active();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $specialties = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json($specialties);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Reuse an existing (possibly inactive) entry with the same name instead of
        // creating a duplicate — this is a shared catalog other users add to as well.
        $specialty = Specialty::firstOrNew(['name' => $validated['name']]);
        $specialty->is_active = true;
        $specialty->created_by = $specialty->created_by ?? auth()->id();
        $specialty->save();

        return response()->json($specialty, 201);
    }
}
