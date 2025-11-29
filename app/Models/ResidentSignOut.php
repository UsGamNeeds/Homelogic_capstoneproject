<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Loggable;
use Carbon\Carbon;

class ResidentSignOut extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $fillable = [
        'resident_id',
        'branch_id',
        'facility_id',
        'sign_out_at',
        'sign_in_at',
        'destination',
        'purpose',
        'accompanied_by',
        'expected_return_at',
        'emergency_contact_notified',
        'notes',
        'is_active',
        'created_by',
        'signed_in_by',
    ];

    protected $casts = [
        'sign_out_at' => 'datetime',
        'sign_in_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'emergency_contact_notified' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_in_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', now());
    }

    public function scopeForResident($query, $residentId)
    {
        return $query->where('resident_id', $residentId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Accessors
    public function getIsOverdueAttribute(): bool
    {
        return $this->is_active
            && $this->expected_return_at !== null
            && $this->expected_return_at->isPast();
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->sign_in_at) {
            return null;
        }

        return $this->sign_out_at->diffInMinutes($this->sign_in_at);
    }

    // Methods
    public function signIn(User $user, ?string $notes = null): void
    {
        $this->sign_in_at = now();
        $this->signed_in_by = $user->id;
        $this->is_active = false;
        
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . $notes;
        }
        
        $this->save();
    }
}

