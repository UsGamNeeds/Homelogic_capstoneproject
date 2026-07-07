<?php

namespace App\Events;

use App\Models\VitalSign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VitalSignCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $vitalSign;

    /**
     * Create a new event instance.
     */
    public function __construct(VitalSign $vitalSign)
    {
        $this->vitalSign = $vitalSign->load([
            'resident',
            'branch',
            'takenBy',
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to facility
        if ($this->vitalSign->branch?->facility_id) {
            $channels[] = new Channel('facility.'.$this->vitalSign->branch->facility_id);
        }

        // Broadcast to branch
        if ($this->vitalSign->branch_id) {
            $channels[] = new Channel('branch.'.$this->vitalSign->branch_id);
        }

        // Broadcast to resident-specific channel
        if ($this->vitalSign->resident_id) {
            $channels[] = new Channel('resident.'.$this->vitalSign->resident_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'vital.sign.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Relations can be null (e.g. a soft-deleted resident excluded by its
        // global scope). Resolve them defensively so broadcastWith never throws
        // and floods the queue with failed BroadcastEvent jobs.
        $resident = $this->vitalSign->resident;
        $takenBy = $this->vitalSign->takenBy;

        return [
            'id' => $this->vitalSign->id,
            'resident_id' => $this->vitalSign->resident_id,
            'branch_id' => $this->vitalSign->branch_id,
            'taken_by' => $this->vitalSign->taken_by,
            'measurement_date' => $this->vitalSign->measurement_date?->toIso8601String(),
            'status' => $this->vitalSign->status,
            'systolic' => $this->vitalSign->systolic,
            'diastolic' => $this->vitalSign->diastolic,
            'temperature' => $this->vitalSign->temperature,
            'pulse' => $this->vitalSign->pulse,
            'oxygen_saturation' => $this->vitalSign->oxygen_saturation,
            'pain_level' => $this->vitalSign->pain_level,
            'resident' => $resident ? [
                'id' => $resident->id,
                'name' => trim(($resident->first_name ?? '').' '.($resident->last_name ?? '')),
            ] : null,
            'taken_by_user' => $takenBy ? [
                'id' => $takenBy->id,
                'name' => trim(($takenBy->first_name ?? '').' '.($takenBy->last_name ?? '')),
            ] : null,
            'created_at' => $this->vitalSign->created_at?->toIso8601String(),
        ];
    }
}
