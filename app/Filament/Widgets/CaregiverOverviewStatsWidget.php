<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Resident;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\VitalSign;
use App\Models\LeaveRequest;

class CaregiverOverviewStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id();
        
        // Get residents assigned to this caregiver
        $assignedResidents = Resident::whereHas('assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->count();
        
        // Today's appointments for assigned residents
        $todayAppointments = Appointment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereDate('appointment_date', today())->count();
        
        // Pending assessments for assigned residents
        $pendingAssessments = Assessment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereNotIn('status', ['approved', 'archived'])->count();
        
        // Vitals recorded today
        $todayVitals = VitalSign::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereDate('measurement_date', today())->count();
        
        // Pending leave requests
        $pendingLeaveRequests = LeaveRequest::where('staff_id', $userId)
            ->where('status', 'pending')->count();
        
        // Upcoming appointments this week
        $weekAppointments = Appointment::whereHas('resident.assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->whereBetween('appointment_date', [today(), today()->addDays(7)])->count();
        
        return [
            Stat::make('My Residents', $assignedResidents)
                ->description('Assigned to me')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make("Today's Appointments", $todayAppointments)
                ->description('Scheduled meetings')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
                
            Stat::make('Pending Assessments', $pendingAssessments)
                ->description('Awaiting completion')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
                
            Stat::make('Vitals Recorded', $todayVitals)
                ->description('Today')
                ->descriptionIcon('heroicon-m-heart')
                ->color('danger'),
                
            Stat::make('Leave Requests', $pendingLeaveRequests)
                ->description('Pending approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
                
            Stat::make('Weekly Appointments', $weekAppointments)
                ->description('Next 7 days')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
        ];
    }
}

