<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Loggable;
use Carbon\Carbon;

class Visitor extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $fillable = [
        'branch_id',
        'facility_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'visit_purpose',
        'visiting_resident_id',
        'visiting_staff_id',
        'check_in_at',
        'check_out_at',
        'expected_duration_minutes',
        'notes',
        'is_active',
        'checked_in_by',
        'checked_out_by',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'expected_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function visitingResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'visiting_resident_id');
    }

    public function visitingStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visiting_staff_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeVisitingResident($query, $residentId)
    {
        return $query->where('visiting_resident_id', $residentId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('check_in_at', today());
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->check_out_at) {
            return null;
        }

        return $this->check_in_at->diffInMinutes($this->check_out_at);
    }

    public function getIsCheckedInAttribute(): bool
    {
        return $this->is_active && $this->check_out_at === null;
    }

    // Methods
    public function checkOut(User $user, ?string $notes = null): void
    {
        $this->check_out_at = now();
        $this->checked_out_by = $user->id;
        $this->is_active = false;
        
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . $notes;
        }
        
        $this->save();
    }
}

