<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with(['resident', 'healthcareProvider', 'appointmentType']);

        // Filter by date
        if ($request->has('date_filter')) {
            $filter = $request->get('date_filter');
            if ($filter === 'upcoming') {
                $query->where('appointment_date', '>=', now());
            } elseif ($filter === 'past') {
                $query->where('appointment_date', '<', now());
            }
        }

        // Filter by status
        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->get('status'));
        }

        // Filter by resident
        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->get('resident_id'));
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->paginate($request->get('per_page', 15));

        return response()->json($appointments);
    }

    public function show($id): JsonResponse
    {
        $appointment = Appointment::with(['resident', 'healthcareProvider', 'appointmentType'])
            ->findOrFail($id);

        return response()->json($appointment);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        $appointment = Appointment::findOrFail($id);
        $appointment->status = $request->get('status');
        $appointment->save();

        return response()->json($appointment->load(['resident', 'healthcareProvider']));
    }
}

