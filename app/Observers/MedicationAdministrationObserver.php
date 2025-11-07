<?php

namespace App\Observers;

use App\Models\MedicationAdministration;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class MedicationAdministrationObserver
{
    /**
     * Handle the MedicationAdministration "created" event.
     */
    public function created(MedicationAdministration $administration): void
    {
        // Only create notification for completed administrations
        if ($administration->status !== 'completed') {
            return;
        }

        // Load relationships
        $administration->load(['resident.assignments.caregiver', 'medication.drug', 'administeredBy']);

        // Get assigned caregivers for this resident
        $caregivers = $administration->resident?->assignments
            ->where('is_active', true)
            ->pluck('caregiver')
            ->filter();
        
        // If no caregivers, notify all admins/managers
        if ($caregivers->isEmpty()) {
            $caregivers = User::whereIn('role', ['administrator', 'admin', 'manager', 'super_admin'])
                ->where('is_active', true)
                ->get();
        }

        // Also notify the person who administered it (if different from assigned caregivers)
        $administeredBy = $administration->administeredBy;
        if ($administeredBy) {
            $alreadyIncluded = $caregivers->contains(function ($caregiver) use ($administeredBy) {
                return $caregiver->id === $administeredBy->id;
            });
            if (!$alreadyIncluded) {
                $caregivers->push($administeredBy);
            }
        }

        foreach ($caregivers as $caregiver) {
            $medicationName = $administration->medication->drug?->name ?? $administration->medication->name ?? 'Medication';
            $residentName = trim(($administration->resident->first_name ?? '') . ' ' . ($administration->resident->last_name ?? ''));
            $administeredByName = trim(($administration->administeredBy->first_name ?? '') . ' ' . ($administration->administeredBy->last_name ?? ''));
            
            // Format administered time
            $administeredAt = $administration->administered_at 
                ? Carbon::parse($administration->administered_at)->format('M d, Y g:i A') 
                : 'TBD';
            
            // Build message
            $message = "{$medicationName} was administered to {$residentName}";
            if ($administeredByName) {
                $message .= " by {$administeredByName}";
            }
            $message .= " on {$administeredAt}";
            
            if ($administration->dosage_given) {
                $message .= " (Dosage: {$administration->dosage_given})";
            }
            
            Notification::create([
                'user_id' => $caregiver->id,
                'type' => 'medication_administered',
                'title' => 'Medication Administered',
                'message' => $message,
                'icon' => 'pill',
                'icon_color' => 'text-green-600',
                'action_url' => '/medications',
                'metadata' => [
                    'medication_administration_id' => $administration->id,
                    'medication_id' => $administration->medication_id,
                    'resident_id' => $administration->resident_id,
                    'administered_by' => $administration->administered_by,
                ],
            ]);
        }
    }
}

