<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Resident::with(['branch', 'facility']);

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('room_number', 'like', "%{$search}%");
            });
        }

        // Filter by branch
        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        $residents = $query->paginate($request->get('per_page', 15));

        return response()->json($residents);
    }

    public function show($id): JsonResponse
    {
        $resident = Resident::with(['branch', 'facility', 'appointments', 'vitalSigns'])
            ->findOrFail($id);

        return response()->json($resident);
    }

    public function appointments($id): JsonResponse
    {
        $resident = Resident::findOrFail($id);
        $appointments = $resident->appointments()
            ->with(['healthcareProvider'])
            ->orderBy('appointment_date', 'desc')
            ->paginate(15);

        return response()->json($appointments);
    }

    public function vitals($id): JsonResponse
    {
        $resident = Resident::findOrFail($id);
        $vitals = $resident->vitalSigns()
            ->orderBy('measurement_date', 'desc')
            ->paginate(15);

        return response()->json($vitals);
    }
}

