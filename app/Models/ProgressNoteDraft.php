<?php

namespace App\Models;

use App\Models\Scopes\FacilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI-drafted progress note awaiting human review. This is NOT a progress
 * note — it never appears anywhere a real progress note would, and nothing
 * here is acted on until a reviewer approves it (see ProgressNoteAgentService
 * and AiProgressNoteController::approve). Approval creates an ordinary TLog
 * row through TLog::create(), leaving the existing T-Logs feature untouched.
 */
class ProgressNoteDraft extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_FLAGGED = 'flagged';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected static function booted()
    {
        static::addGlobalScope(new FacilityScope);
    }

    protected $fillable = [
        'resident_id',
        'branch_id',
        'facility_id',
        'created_by',
        'raw_shift_entry',
        'window_label',
        'draft_json',
        'summary_json',
        'medication_check_json',
        'mode',
        'model',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        't_log_id',
    ];

    protected $casts = [
        'draft_json' => 'array',
        'summary_json' => 'array',
        'medication_check_json' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function tLog(): BelongsTo
    {
        return $this->belongsTo(TLog::class, 't_log_id');
    }

    public function getFlagsAttribute(): array
    {
        return $this->summary_json['flags'] ?? [];
    }

    public function hasFlags(): bool
    {
        return ! empty($this->flags);
    }
}
