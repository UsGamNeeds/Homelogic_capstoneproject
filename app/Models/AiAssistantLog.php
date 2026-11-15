<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantLog extends Model
{
    protected $fillable = [
        'facility_id',
        'resident_id',
        'user_id',
        'feature',
        'mode',
        'model',
        'tool_call_count',
        'tool_calls',
        'prompt_redacted',
        'response_redacted',
        'identifier_scan_hits',
        'flags',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'identifier_scan_hits' => 'array',
        'flags' => 'array',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
