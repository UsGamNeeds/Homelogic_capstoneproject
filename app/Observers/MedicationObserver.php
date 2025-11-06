<?php

namespace App\Observers;

use App\Models\Medication;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class MedicationObserver
{
    /**
     * Handle the Medication "created" event.
     */
    public function created(Medication $medication): void
    {
        // Only create notification for active medications
        if (!$medication->is_active) {
            return;
        }

        // Load relationships
        $medication->load(['resident.assignments.caregiver', 'drug']);

        // Get assigned caregivers for this resident
        $caregivers = $medication->resident?->assignments
            ->where('is_active', true)
            ->pluck('caregiver')
            ->filter();
        
        // If no caregivers, notify all admins/managers
        if ($caregivers->isEmpty()) {
            $caregivers = User::whereIn('role', ['administrator', 'admin', 'manager', 'super_admin'])
                ->where('is_active', true)
                ->get();
        }

        foreach ($caregivers as $caregiver) {
            $medicationName = $medication->drug?->name ?? $medication->name ?? 'New Medication';
            $residentName = trim(($medication->resident->first_name ?? '') . ' ' . ($medication->resident->last_name ?? ''));
            
            // Format start date
            $startDate = $medication->start_date 
                ? Carbon::parse($medication->start_date)->format('M d, Y') 
                : 'TBD';
            
            // Get administration times
            $times = [];
            for ($i = 1; $i <= 4; $i++) {
                $timeField = "time_{$i}";
                if ($medication->$timeField) {
                    try {
                        $time = Carbon::parse($medication->$timeField);
                        $times[] = $time->format('g:i A');
                    } catch (\Exception $e) {
                        // Skip invalid time
                    }
                }
            }
            $timesStr = !empty($times) ? ' at ' . implode(', ', $times) : '';
            
            Notification::create([
                'user_id' => $caregiver->id,
                'type' => 'medication_created',
                'title' => 'New Medication Added',
                'message' => "{$medicationName} has been added for {$residentName}. Start date: {$startDate}{$timesStr}",
                'icon' => 'pill',
                'icon_color' => 'text-red-600',
                'action_url' => '/app/medications',
                'metadata' => [
                    'medication_id' => $medication->id,
                    'resident_id' => $medication->resident_id,
                    'medication_name' => $medicationName,
                ],
            ]);
        }
    }
}

