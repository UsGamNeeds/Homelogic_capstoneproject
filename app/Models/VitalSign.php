<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Traits\Loggable;

class VitalSign extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $fillable = [
        'resident_id',
        'branch_id',
        'measurement_date',
        'systolic',
        'diastolic',
        'temperature',
        'pulse',
        'oxygen_saturation',
        'pain_level',
        'pain_description',
        'reason_declined',
        'status',
        'notes',
        'taken_by',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'systolic' => 'integer',
        'diastolic' => 'integer',
        'temperature' => 'decimal:2',
        'pulse' => 'integer',
        'oxygen_saturation' => 'integer',
        'pain_level' => 'integer',
    ];

    // Relationships
    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function takenBy()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    // Scopes
    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByResident($query, $residentId)
    {
        return $query->where('resident_id', $residentId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('measurement_date', [$startDate, $endDate]);
    }

    public function scopeCritical($query)
    {
        return $query->where('status', 'critical');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    // Accessors
    public function getBloodPressureAttribute()
    {
        if ($this->systolic && $this->diastolic) {
            return $this->systolic . '/' . $this->diastolic;
        }
        return null;
    }

    public function getFormattedTemperatureAttribute()
    {
        if ($this->temperature) {
            return $this->temperature . '°F';
        }
        return null;
    }

    public function getFormattedPulseAttribute()
    {
        if ($this->pulse) {
            return $this->pulse . ' BPM';
        }
        return null;
    }

    public function getFormattedOxygenSaturationAttribute()
    {
        if ($this->oxygen_saturation) {
            return $this->oxygen_saturation . '%';
        }
        return null;
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'approved' => 'success',
            'pending_review' => 'warning',
            'declined' => 'gray',
            'critical' => 'danger',
            default => 'gray',
        };
    }

    public function getFormattedDateAttribute()
    {
        return $this->measurement_date?->format('m/d/Y');
    }

    // Helper methods for range checking
    public function checkBloodPressureRange()
    {
        $systolicRange = VitalRange::getRangeForParameter('systolic');
        $diastolicRange = VitalRange::getRangeForParameter('diastolic');
        
        if (!$systolicRange || !$diastolicRange || !$this->systolic || !$this->diastolic) {
            return 'unknown';
        }

        // Check for critical values
        if ($this->systolic >= $systolicRange->max_critical || $this->systolic <= $systolicRange->min_critical ||
            $this->diastolic >= $diastolicRange->max_critical || $this->diastolic <= $diastolicRange->min_critical) {
            return 'critical';
        }

        // Check for warning values
        if ($this->systolic >= $systolicRange->max_warning || $this->systolic <= $systolicRange->min_warning ||
            $this->diastolic >= $diastolicRange->max_warning || $this->diastolic <= $diastolicRange->min_warning) {
            return 'warning';
        }

        // Check for normal values
        if ($this->systolic >= $systolicRange->min_normal && $this->systolic <= $systolicRange->max_normal &&
            $this->diastolic >= $diastolicRange->min_normal && $this->diastolic <= $diastolicRange->max_normal) {
            return 'normal';
        }

        return 'unknown';
    }

    public function checkTemperatureRange()
    {
        $range = VitalRange::getRangeForParameter('temperature');
        
        if (!$range || !$this->temperature) {
            return 'unknown';
        }

        if ($this->temperature >= $range->max_critical || $this->temperature <= $range->min_critical) {
            return 'critical';
        }

        if ($this->temperature >= $range->max_warning || $this->temperature <= $range->min_warning) {
            return 'warning';
        }

        if ($this->temperature >= $range->min_normal && $this->temperature <= $range->max_normal) {
            return 'normal';
        }

        return 'unknown';
    }

    public function checkPulseRange()
    {
        $range = VitalRange::getRangeForParameter('pulse');
        
        if (!$range || !$this->pulse) {
            return 'unknown';
        }

        if ($this->pulse >= $range->max_critical || $this->pulse <= $range->min_critical) {
            return 'critical';
        }

        if ($this->pulse >= $range->max_warning || $this->pulse <= $range->min_warning) {
            return 'warning';
        }

        if ($this->pulse >= $range->min_normal && $this->pulse <= $range->max_normal) {
            return 'normal';
        }

        return 'unknown';
    }

    public function checkOxygenSaturationRange()
    {
        $range = VitalRange::getRangeForParameter('oxygen_saturation');
        
        if (!$range || !$this->oxygen_saturation) {
            return 'unknown';
        }

        if ($this->oxygen_saturation <= $range->min_critical) {
            return 'critical';
        }

        if ($this->oxygen_saturation <= $range->min_warning) {
            return 'warning';
        }

        if ($this->oxygen_saturation >= $range->min_normal && $this->oxygen_saturation <= $range->max_normal) {
            return 'normal';
        }

        return 'unknown';
    }

    // Auto-determine status based on ranges
    public function determineStatus()
    {
        $bloodPressureStatus = $this->checkBloodPressureRange();
        $temperatureStatus = $this->checkTemperatureRange();
        $pulseStatus = $this->checkPulseRange();
        $oxygenStatus = $this->checkOxygenSaturationRange();

        // If any parameter is critical, status is critical
        if (in_array('critical', [$bloodPressureStatus, $temperatureStatus, $pulseStatus, $oxygenStatus])) {
            return 'critical';
        }

        // If any parameter is warning, status is pending review
        if (in_array('warning', [$bloodPressureStatus, $temperatureStatus, $pulseStatus, $oxygenStatus])) {
            return 'pending_review';
        }

        // If all parameters are normal, status is approved
        if (!in_array('unknown', [$bloodPressureStatus, $temperatureStatus, $pulseStatus, $oxygenStatus]) &&
            !in_array('critical', [$bloodPressureStatus, $temperatureStatus, $pulseStatus, $oxygenStatus]) &&
            !in_array('warning', [$bloodPressureStatus, $temperatureStatus, $pulseStatus, $oxygenStatus])) {
            return 'approved';
        }

        return 'pending_review';
    }
}
