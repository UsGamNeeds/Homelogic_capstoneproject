<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentAttachment extends Model
{
    protected $fillable = [
        'appointment_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = ['file_url', 'download_url', 'file_size_human'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->download_url;
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (! $this->file_path || ! $this->appointment_id || ! $this->id) {
            return null;
        }

        return "/api/v1/appointments/{$this->appointment_id}/attachments/{$this->id}/download";
    }

    public function getFileSizeHumanAttribute(): ?string
    {
        if (! $this->file_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }
}
