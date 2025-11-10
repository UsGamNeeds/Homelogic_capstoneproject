<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\FormatsPhoneNumbers;

class HealthcareProvider extends Model
{
    use HasFactory, SoftDeletes;
    use FormatsPhoneNumbers;

    protected $fillable = [
        'name',
        'specialty',
        'phone',
        'email',
        'contact_info',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Static methods for dropdown options
    public static function getOptions()
    {
        return self::active()->pluck('name', 'id')->toArray();
    }

    // Accessors
    public function getFullContactInfoAttribute()
    {
        $info = [];
        if ($this->phone) $info[] = "Phone: {$this->phone}";
        if ($this->email) $info[] = "Email: {$this->email}";
        if ($this->contact_info) $info[] = $this->contact_info;
        
        return implode(' | ', $info);
    }

    protected function phone(): Attribute
    {
        return $this->phoneAttribute();
    }
}
