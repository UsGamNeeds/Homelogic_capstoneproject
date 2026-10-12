<?php

namespace App\Models;

use App\Models\Scopes\FacilityScope;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BloodGlucoseReport extends Model
{
    use Loggable, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new FacilityScope);
    }

    protected $fillable = [
        'resident_id',
        'branch_id',
        'reported_by',
        'report_date',
        'notification_level',
        'reading_date',
        'reading_time',
        'value',
        'method_used',
        'fasting',
        'hours_since_last_meal',
        'insulin_given',
        'nurse_notified',
        'treatment_type',
        'medication_name',
        'medication_amount',
        'medication_route',
        'medication_strength',
        'medication_frequency',
        'comments',
    ];

    protected $casts = [
        'report_date' => 'date',
        'reading_date' => 'date',
        'value' => 'decimal:1',
        'fasting' => 'boolean',
        'hours_since_last_meal' => 'decimal:1',
        'insulin_given' => 'boolean',
        'nurse_notified' => 'boolean',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
