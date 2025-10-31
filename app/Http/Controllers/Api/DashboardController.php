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
        $user = auth()->user();
        
        // Check if user is a caregiver
        $isCaregiver = in_array($user->role, ['caregiver', 'care_giver', 'nurse', 'registered_nurse', 'licensed_nurse']);
        
        if ($isCaregiver) {
            return $this->caregiverStats($user);
        }
        
        // Admin stats
        $stats = [
            'total_residents' => Resident::count(),
            'active_residents' => Resident::where('status', 'active')->count(),
            'today_appointments' => Appointment::whereDate('appointment_date', today())->count(),
            'upcoming_appointments' => Appointment::where('appointment_date', '>=', now())
                ->where('status', 'scheduled')
                ->count(),
            'today_vitals' => VitalSign::whereDate('measurement_date', today())->count(),
            'total_staff' => User::where('is_active', true)->count(),
            'pending_assessments' => 0,
        ];

        return response()->json($stats);
    }
    
    private function caregiverStats($user): JsonResponse
    {
        $userId = $user->id;
        
        // Get residents assigned to this caregiver
        $assignedResidents = Resident::whereHas('assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->count();
        
        // Today's appointments for assigned residents
        $todayAppointments = Appointment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereDate('appointment_date', today())->count();
        
        // Pending assessments for assigned residents
        $pendingAssessments = \App\Models\Assessment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereNotIn('status', ['approved', 'archived'])->count();
        
        // Vitals recorded today
        $todayVitals = VitalSign::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereDate('measurement_date', today())->count();
        
        // Pending leave requests
        $pendingLeaveRequests = \App\Models\LeaveRequest::where('staff_id', $userId)
            ->where('status', 'pending')->count();
        
        // Upcoming appointments this week
        $weekAppointments = Appointment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereBetween('appointment_date', [today(), today()->addDays(7)])->count();
        
        return response()->json([
            'assigned_residents' => $assignedResidents,
            'todays_appointments' => $todayAppointments,
            'pending_assessments' => $pendingAssessments,
            'today_vitals' => $todayVitals,
            'pending_leave_requests' => $pendingLeaveRequests,
            'week_appointments' => $weekAppointments,
            'user_type' => 'caregiver',
        ]);
    }
}

