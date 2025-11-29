<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Loggable;
use Carbon\Carbon;

class StaffClockIn extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $fillable = [
        'staff_id',
        'branch_id',
        'facility_id',
        'clock_in_at',
        'clock_out_at',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_out_latitude',
        'clock_out_longitude',
        'total_hours',
        'notes',
        'is_active',
        'clock_method',
        'employee_identifier',
    ];

    protected $casts = [
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'clock_in_latitude' => 'decimal:8',
        'clock_in_longitude' => 'decimal:8',
        'clock_out_latitude' => 'decimal:8',
        'clock_out_longitude' => 'decimal:8',
        'total_hours' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('clock_in_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('clock_in_at', today());
    }

    // Accessors
    public function getDurationAttribute(): ?float
    {
        if (!$this->clock_out_at) {
            return null;
        }

        return round($this->clock_in_at->diffInMinutes($this->clock_out_at) / 60, 2);
    }

    public function getIsClockedInAttribute(): bool
    {
        return $this->is_active && $this->clock_out_at === null;
    }

    // Methods
    public function clockOut(?float $latitude = null, ?float $longitude = null): void
    {
        $this->clock_out_at = now();
        $this->clock_out_latitude = $latitude;
        $this->clock_out_longitude = $longitude;
        $this->is_active = false;
        $this->total_hours = $this->duration;
        $this->save();
    }
}

