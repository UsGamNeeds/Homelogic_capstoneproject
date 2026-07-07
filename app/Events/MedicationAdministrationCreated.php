<?php

namespace App\Events;

use App\Models\MedicationAdministration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MedicationAdministrationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $administration;

    /**
     * Create a new event instance.
     */
    public function __construct(MedicationAdministration $administration)
    {
        $this->administration = $administration->load([
            'medication.drug',
            'resident',
            'branch',
            'administeredBy',
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to facility
        if ($this->administration->branch?->facility_id) {
            $channels[] = new Channel('facility.'.$this->administration->branch->facility_id);
        }

        // Broadcast to branch
        if ($this->administration->branch_id) {
            $channels[] = new Channel('branch.'.$this->administration->branch_id);
        }

        // Broadcast to resident-specific channel
        if ($this->administration->resident_id) {
            $channels[] = new Channel('resident.'.$this->administration->resident_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'medication.administration.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Relations can be null (e.g. a soft-deleted medication excluded by its
        // global scope). Resolve them defensively so broadcastWith never throws
        // and floods the queue with failed BroadcastEvent jobs.
        $medication = $this->administration->medication;
        $resident = $this->administration->resident;
        $administeredBy = $this->administration->administeredBy;

        return [
            'id' => $this->administration->id,
            'medication_id' => $this->administration->medication_id,
            'resident_id' => $this->administration->resident_id,
            'branch_id' => $this->administration->branch_id,
            'administered_by' => $this->administration->administered_by,
            'administered_at' => $this->administration->administered_at?->toIso8601String(),
            'status' => $this->administration->status,
            'dosage_given' => $this->administration->dosage_given,
            'medication' => $medication ? [
                'id' => $medication->id,
                'name' => $medication->drug?->name ?? $medication->name,
            ] : null,
            'resident' => $resident ? [
                'id' => $resident->id,
                'name' => trim(($resident->first_name ?? '').' '.($resident->last_name ?? '')),
            ] : null,
            'administered_by_user' => $administeredBy ? [
                'id' => $administeredBy->id,
                'name' => trim(($administeredBy->first_name ?? '').' '.($administeredBy->last_name ?? '')),
            ] : null,
            'created_at' => $this->administration->created_at?->toIso8601String(),
        ];
    }
}
