<?php

namespace App\Http\Controllers\Api;

use App\Models\HealthcareProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthcareProviderController extends BaseApiController
{
    /**
     * Search the shared healthcare provider catalog (used by the Consultant search
     * field on the appointment form). Not facility-scoped — providers are shared
     * across the whole system, same as HealthcareProvider::getOptions().
     */
    public function index(Request $request): JsonResponse
    {
        $query = HealthcareProvider::query()->active();

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('specialty', 'like', "%{$search}%");
            });
        }

        $providers = $query->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json($providers);
    }

    public function show($id): JsonResponse
    {
        $provider = HealthcareProvider::findOrFail($id);

        return response()->json($provider);
    }

    /**
     * Add a new consultant to the shared catalog — any authenticated user can add
     * one, and it's immediately searchable/selectable by everyone else.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $provider = HealthcareProvider::firstOrNew(['name' => $validated['name']]);
        $provider->fill($validated);
        $provider->is_active = true;
        $provider->save();

        return response()->json($provider, 201);
    }
}
