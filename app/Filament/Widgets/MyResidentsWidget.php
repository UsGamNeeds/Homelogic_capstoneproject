<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Resident;
use App\Models\VitalSign;

class MyResidentsWidget extends Widget
{
    protected static string $view = 'filament.widgets.my-residents-widget';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public function getResidents(): array
    {
        $userId = auth()->id();
        
        $residents = Resident::whereHas('assignments', function($q) use ($userId) {
            $q->where('caregiver_id', $userId)->where('is_active', true);
        })->with(['branch', 'vitalSigns' => function($query) {
            $query->latest('measurement_date')->limit(1);
        }])->get();

        return $residents->map(function($resident) {
            $latestVitals = $resident->vitalSigns->first();
            return [
                'id' => $resident->id,
                'name' => $resident->name,
                'room' => $resident->room,
                'branch' => $resident->branch->name,
                'last_vitals' => $latestVitals ? $latestVitals->measurement_date->format('M d, Y H:i') : 'N/A',
                'health_status' => $this->getHealthStatus($latestVitals),
            ];
        })->toArray();
    }

    private function getHealthStatus($vitals): string
    {
        if (!$vitals) return 'unknown';
        
        if ($vitals->systolic > 140 || 
            $vitals->diastolic > 90 ||
            $vitals->pulse > 100) {
            return 'attention';
        } elseif ($vitals->systolic < 90 || 
                 $vitals->diastolic < 60 ||
                 $vitals->pulse < 50) {
            return 'caution';
        }
        
        return 'good';
    }
}