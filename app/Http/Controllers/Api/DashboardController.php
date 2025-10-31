<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Models\Appointment;
use App\Models\VitalSign;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_residents' => Resident::count(),
            'active_residents' => Resident::where('status', 'active')->count(),
            'today_appointments' => Appointment::whereDate('appointment_date', today())->count(),
            'upcoming_appointments' => Appointment::where('appointment_date', '>=', now())
                ->where('status', 'scheduled')
                ->count(),
            'today_vitals' => VitalSign::whereDate('measurement_date', today())->count(),
            'total_staff' => User::where('is_active', true)->count(),
            'pending_assessments' => 0, // Add when you have assessment model
        ];

        return response()->json($stats);
    }
}

