<?php

namespace App\Observers;

use App\Models\SleepRecord;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class SleepRecordObserver
{
    /**
     * Handle the SleepRecord "created" event.
     */
    public function created(SleepRecord $sleepRecord): void
    {
        // Load relationships
        $sleepRecord->load(['resident.assignments.caregiver', 'createdBy']);

        // Get assigned caregivers for this resident
        $caregivers = $sleepRecord->resident?->assignments
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
            $residentName = trim(($sleepRecord->resident->first_name ?? '') . ' ' . ($sleepRecord->resident->last_name ?? ''));
            $createdByName = $sleepRecord->createdBy 
                ? trim(($sleepRecord->createdBy->first_name ?? '') . ' ' . ($sleepRecord->createdBy->last_name ?? ''))
                : 'Staff';
            $sleepDate = $sleepRecord->sleep_date ? Carbon::parse($sleepRecord->sleep_date)->format('M d, Y') : 'TBD';
            $sleepHours = $sleepRecord->total_sleep_hours ?? 0;
            $quality = $sleepRecord->sleep_quality_text ?? 'Not rated';
            
            Notification::create([
                'user_id' => $caregiver->id,
                'type' => 'sleep_record',
                'title' => 'Sleep Record Added',
                'message' => "Sleep record for {$residentName} was recorded by {$createdByName} on {$sleepDate}. Duration: {$sleepHours} hours, Quality: {$quality}",
                'icon' => 'moon',
                'icon_color' => 'text-[#2D5016]',
                'action_url' => '/app/sleep',
                'metadata' => [
                    'sleep_record_id' => $sleepRecord->id,
                    'resident_id' => $sleepRecord->resident_id,
                    'total_sleep_hours' => $sleepHours,
                ],
            ]);
        }
    }
}


