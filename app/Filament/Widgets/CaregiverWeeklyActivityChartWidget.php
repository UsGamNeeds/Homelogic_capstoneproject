<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Resident;
use App\Models\VitalSign;
use App\Models\Assessment;
use Carbon\Carbon;

class CaregiverWeeklyActivityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Weekly Activity';
    protected static ?int $sort = 2;
    
    protected function getData(): array
    {
        $userId = auth()->id();
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();
        
        // Get assigned residents
        $assignedResidents = Resident::whereHas('assignments', function($query) use ($userId) {
            $query->where('caregiver_id', $userId)->where('is_active', true);
        })->get();
        
        $residentIds = $assignedResidents->pluck('id');
        
        // Vital signs data for the week
        $vitalSigns = VitalSign::whereIn('resident_id', $residentIds)
            ->whereBetween('measurement_date', [$weekStart, $weekEnd])
            ->orderBy('measurement_date')
            ->get();
        
        // Group by day
        $vitalSignsByDay = $vitalSigns->groupBy(function($item) {
            if (!$item->measurement_date) {
                return null;
            }
            return $item->measurement_date instanceof Carbon 
                ? $item->measurement_date->format('Y-m-d')
                : Carbon::parse($item->measurement_date)->format('Y-m-d');
        });
        
        // Create chart data
        $chartData = [];
        $labels = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('M j');
            
            $dayVitals = $vitalSignsByDay->get($dateStr, collect());
            $chartData[] = $dayVitals->count();
        }
        
        // Assessment completion data
        $assessments = Assessment::whereIn('resident_id', $residentIds)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();
        
        $assessmentData = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');
            
            $dayAssessments = $assessments->filter(function($item) use ($dateStr) {
                $createdAt = $item->created_at instanceof Carbon 
                    ? $item->created_at
                    : Carbon::parse($item->created_at);
                return $createdAt->format('Y-m-d') === $dateStr;
            });
            
            $assessmentData[] = $dayAssessments->count();
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Vital Signs',
                    'data' => $chartData,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
                [
                    'label' => 'Assessments',
                    'data' => $assessmentData,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
}

