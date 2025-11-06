<?php

namespace App\Observers;

use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class IncidentObserver
{
    /**
     * Handle the Incident "created" event.
     */
    public function created(Incident $incident): void
    {
        // Load relationships
        $incident->load(['resident', 'reportedBy']);

        // Always notify all admins/managers for incidents (critical events)
        $admins = User::whereIn('role', ['administrator', 'admin', 'manager', 'super_admin'])
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $residentName = trim(($incident->resident->first_name ?? '') . ' ' . ($incident->resident->last_name ?? ''));
            $reportedByName = $incident->reportedBy 
                ? trim(($incident->reportedBy->first_name ?? '') . ' ' . ($incident->reportedBy->last_name ?? ''))
                : 'Staff';
            $incidentDate = $incident->incident_date ? Carbon::parse($incident->incident_date)->format('M d, Y g:i A') : 'TBD';
            
            // Determine icon color based on severity
            $iconColor = match($incident->severity ?? 'low') {
                'critical' => 'text-red-600',
                'high' => 'text-orange-600',
                'medium' => 'text-yellow-600',
                default => 'text-[#8B4513]',
            };
            
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'incident_reported',
                'title' => 'New Incident Reported',
                'message' => "A {$incident->severity} severity {$incident->incident_type} incident involving {$residentName} was reported by {$reportedByName} on {$incidentDate}",
                'icon' => 'alert-circle',
                'icon_color' => $iconColor,
                'action_url' => '/app/incidents',
                'metadata' => [
                    'incident_id' => $incident->id,
                    'resident_id' => $incident->resident_id,
                    'incident_type' => $incident->incident_type,
                    'severity' => $incident->severity,
                ],
            ]);
        }
    }
}


