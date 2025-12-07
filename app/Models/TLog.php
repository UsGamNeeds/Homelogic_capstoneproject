<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Loggable;
use App\Models\Scopes\FacilityScope;

class TLog extends Model
{
    use Loggable;

    protected $table = 't_logs';

    protected static function booted()
    {
        static::addGlobalScope(new FacilityScope);
    }

    protected $fillable = [
        'resident_id',
        'branch_id',
        'types',
        'notification_level',
        'summary',
        'description',
        'reporter_id',
        'reported_on',
        'entered_by_id',
    ];

    protected $casts = [
        'types' => 'array',
        'reported_on' => 'datetime',
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

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TLogAttachment::class, 't_log_id');
    }

    // Scopes
    public function scopeForResident($query, $residentId)
    {
        return $query->where('resident_id', $residentId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByType($query, $type)
    {
        return $query->whereJsonContains('types', $type);
    }

    public function scopeByNotificationLevel($query, $level)
    {
        return $query->where('notification_level', $level);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('reported_on', [$startDate, $endDate]);
    }

    // Helper methods
    public function hasType($type): bool
    {
        return in_array($type, $this->types ?? []);
    }

    public function getTypesLabelAttribute(): string
    {
        return implode(', ', array_map('ucfirst', $this->types ?? []));
    }

    public function getNotificationLevelColorAttribute(): string
    {
        return match($this->notification_level) {
            'urgent' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }
}
