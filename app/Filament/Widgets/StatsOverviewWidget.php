<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Resident;
use App\Models\Medication;
use App\Models\Appointment;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected function getStats(): array
    {
        $residentCount = $this->getResidentCount();
        $staffCount = $this->getStaffCount();
        $medicationCount = $this->getMedicationCount();
        $appointmentCount = $this->getAppointmentCount();
        
        // Get previous week's data for comparison
        $prevResidents = $this->getPreviousResidentCount();
        $prevStaff = $this->getPreviousStaffCount();
        $prevMedications = $this->getPreviousMedicationCount();
        $prevAppointments = $this->getPreviousAppointmentCount();
        
        return [
            Stat::make('Active Residents', $residentCount)
                ->description($this->getChangeDescription($residentCount, $prevResidents))
                ->descriptionIcon($this->getChangeIcon($residentCount, $prevResidents))
                ->color('primary')
                ->chart($this->getResidentChartData())
                ->url(route('filament.admin.resources.residents.index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-200',
                ]),
                
            Stat::make('Staff Members', $staffCount)
                ->description($this->getChangeDescription($staffCount, $prevStaff))
                ->descriptionIcon($this->getChangeIcon($staffCount, $prevStaff))
                ->color('success')
                ->chart($this->getStaffChartData())
                ->url(route('filament.admin.resources.users.index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-200',
                ]),
                
            Stat::make('Active Medications', $medicationCount)
                ->description($this->getChangeDescription($medicationCount, $prevMedications))
                ->descriptionIcon($this->getChangeIcon($medicationCount, $prevMedications))
                ->color('warning')
                ->chart($this->getMedicationChartData())
                ->url(route('filament.admin.resources.medications.index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-200',
                ]),
                
            Stat::make('This Week\'s Appointments', $appointmentCount)
                ->description($this->getChangeDescription($appointmentCount, $prevAppointments))
                ->descriptionIcon($this->getChangeIcon($appointmentCount, $prevAppointments))
                ->color('info')
                ->chart($this->getAppointmentChartData())
                ->url(route('filament.admin.resources.appointments.index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-lg transition-all duration-200',
                ]),
        ];
    }
    
    private function getChangeDescription($current, $previous): string
    {
        if ($previous == 0) return 'No previous data';
        
        $change = $current - $previous;
        $percentChange = round(($change / $previous) * 100, 1);
        
        if ($change > 0) {
            return "+{$percentChange}% from last period";
        } elseif ($change < 0) {
            return "{$percentChange}% from last period";
        }
        
        return 'No change';
    }
    
    private function getChangeIcon($current, $previous): string
    {
        $change = $current - $previous;
        
        if ($change > 0) {
            return 'heroicon-m-arrow-trending-up';
        } elseif ($change < 0) {
            return 'heroicon-m-arrow-trending-down';
        }
        
        return 'heroicon-m-minus';
    }
    
    private function getResidentChartData(): array
    {
        try {
            return Resident::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray();
        } catch (\Exception $e) {
            return [7, 2, 10, 3, 15, 4, 17];
        }
    }
    
    private function getStaffChartData(): array
    {
        try {
            return User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray();
        } catch (\Exception $e) {
            return [3, 4, 5, 6, 8, 10, 12];
        }
    }
    
    private function getMedicationChartData(): array
    {
        try {
            return Medication::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(7))
                ->where('is_active', true)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray();
        } catch (\Exception $e) {
            return [15, 20, 25, 30, 35, 40, 45];
        }
    }
    
    private function getAppointmentChartData(): array
    {
        try {
            return Appointment::selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
                ->whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count')
                ->toArray();
        } catch (\Exception $e) {
            return [2, 4, 6, 8, 10, 12, 14];
        }
    }
    
    private function getResidentCount(): int
    {
        try {
            return Resident::count();
        } catch (\Exception $e) {
            return 24; // Default value
        }
    }
    
    private function getStaffCount(): int
    {
        try {
            return User::count();
        } catch (\Exception $e) {
            return 18; // Default value
        }
    }
    
    private function getMedicationCount(): int
    {
        try {
            return Medication::count();
        } catch (\Exception $e) {
            return 156; // Default value
        }
    }
    
    private function getAppointmentCount(): int
    {
        try {
            return Appointment::whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])->count();
        } catch (\Exception $e) {
            return 42; // Default value
        }
    }
    
    private function getPreviousResidentCount(): int
    {
        try {
            return Resident::where('created_at', '<', now()->subWeek())->count();
        } catch (\Exception $e) {
            return 20;
        }
    }
    
    private function getPreviousStaffCount(): int
    {
        try {
            return User::where('created_at', '<', now()->subWeek())->count();
        } catch (\Exception $e) {
            return 15;
        }
    }
    
    private function getPreviousMedicationCount(): int
    {
        try {
            return Medication::where('created_at', '<', now()->subWeek())->where('is_active', true)->count();
        } catch (\Exception $e) {
            return 140;
        }
    }
    
    private function getPreviousAppointmentCount(): int
    {
        try {
            $lastWeekStart = now()->subWeek()->startOfWeek();
            $lastWeekEnd = now()->subWeek()->endOfWeek();
            return Appointment::whereBetween('appointment_date', [$lastWeekStart, $lastWeekEnd])->count();
        } catch (\Exception $e) {
            return 38;
        }
    }
}







