<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FacilitySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'category',
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * The facility this setting belongs to.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the casted value based on the type column.
     */
    public function getCastedValueAttribute()
    {
        $value = $this->value;

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => $value ? json_decode($value, true) : null,
            default => $value,
        };
    }

    /**
     * Set the value attribute and normalise based on type.
     */
    public function setValueAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;

            return;
        }

        switch ($this->type) {
            case 'boolean':
                $this->attributes['value'] = $value ? '1' : '0';

                break;
            case 'integer':
                $this->attributes['value'] = (string) ((int) $value);

                break;
            case 'json':
                $this->attributes['value'] = json_encode($value);

                break;
            default:
                $this->attributes['value'] = (string) $value;
        }
    }
}


