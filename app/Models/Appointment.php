<?php

namespace App\Models;

use App\Models\Scopes\FacilityScope;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new FacilityScope);
    }

    protected $fillable = [
        'title',
        'resident_id',
        'branch_id',
        'appointment_type_id',
        'healthcare_provider_id',
        'appointment_date',
        'appointment_time',
        'original_appointment_date',
        'original_appointment_time',
        'reschedule_reason',
        'provider_name',
        'location',
        'description',
        'status',
        'next_appointment_date',
        'recurrence_pattern',
        'notes',
        'created_by',
        'reported_by',
        'notification_level',
        'end_time',
        'hospital_id',
        'other_provider',
        'specialty',
        'specialty_id',
        'location_type',
        'address_street1',
        'address_street2',
        'address_city',
        'address_state',
        'address_zip',
        'address_country',
        'latitude',
        'longitude',
        'phone',
        'phone_extension',
        'driver',
        'pick_up_at',
        'depart_time',
        'reason_for_appointment',
        'confidential_note',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'appointment_time' => 'string', // Time column returns as string "HH:mm:ss"
        'end_time' => 'string',
        'depart_time' => 'string',
        'original_appointment_date' => 'date',
        'original_appointment_time' => 'string',
        'next_appointment_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
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

    public function appointmentType()
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function healthcareProvider()
    {
        return $this->belongsTo(HealthcareProvider::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function specialtyLookup()
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reportedByUser()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ResidentDocument::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AppointmentAttachment::class);
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

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString());
    }

    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now()->toDateString());
    }

    // Accessors
    public function getFormattedDateTimeAttribute()
    {
        $date = $this->appointment_date->format('M j, Y');
        $time = $this->appointment_time ? $this->appointment_time->format('g:i A') : '';

        return $time ? "{$date} at {$time}" : $date;
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'scheduled' => 'gray',
            'confirmed' => 'blue',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'orange',
            'rescheduled' => 'purple',
            default => 'gray',
        };
    }
}
