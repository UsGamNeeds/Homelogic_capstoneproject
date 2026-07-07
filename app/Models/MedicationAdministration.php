<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationAdministration extends Model
{
    use Loggable;

    /**
     * Medications are soft-deleted, but their dose rows here are not (and the DB
     * onDelete('cascade') only fires on a hard delete). Without this scope, doses
     * recorded before a medication was deleted keep surfacing in the MAR log,
     * dashboard missed-counts and reports as orphaned "missed"/"auto-missed" rows.
     *
     * A raw whereExists on medications.deleted_at keeps the parent visible only
     * while the medication exists, without dragging in the Medication model's
     * other global scopes (FacilityScope etc.). Use withoutGlobalScope('medicationExists')
     * to deliberately reach orphaned rows (e.g. cleanup tooling).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('medicationExists', function (Builder $builder) {
            $builder->whereExists(function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('medications')
                    ->whereColumn('medications.id', 'medication_administrations.medication_id')
                    ->whereNull('medications.deleted_at');
            });
        });
    }

    protected $fillable = [
        'medication_id',
        'resident_id',
        'branch_id',
        'administered_by',
        'administered_at',
        'status',
        'notes',
        'dosage_given',
        'document_path',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
    ];

    // Relationships
    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    // Accessors
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'missed' => 'Missed',
            'refused' => 'Refused',
            'hospital_admission' => 'Hospital Admission',
            'pharmacy_administration_confirm' => 'Pharmacy Administration Confirm',
            default => ucfirst($this->status),
        };
    }
}
