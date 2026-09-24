<?php

namespace App\Http\Controllers\Api;

use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared, growable lookup for the appointment form's Hospital field. Any
 * authenticated user can search the existing list or add a new one.
 */
class HospitalController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Hospital::query()->active();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->get('search').'%');
        }

        $hospitals = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json($hospitals);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $hospital = Hospital::firstOrNew(['name' => $validated['name']]);
        $hospital->fill($validated);
        $hospital->is_active = true;
        $hospital->created_by = $hospital->created_by ?? auth()->id();
        $hospital->save();

        return response()->json($hospital, 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(Hospital::findOrFail($id));
    }
}
