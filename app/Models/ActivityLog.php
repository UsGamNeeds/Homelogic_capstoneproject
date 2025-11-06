<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'log_type',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'context',
        'user_id',
        'branch_id',
        'ip_address',
        'user_agent',
        'level',
        'logged_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'context' => 'array',
        'logged_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch associated with the log
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the subject (model) that was acted upon
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * Scope for filtering by log type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('log_type', $type);
    }

    /**
     * Scope for filtering by event
     */
    public function scopeEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope for filtering by level
     */
    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for filtering by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by subject
     */
    public function scopeForSubject($query, $modelType, $modelId = null)
    {
        $query->where('subject_type', $modelType);
        if ($modelId) {
            $query->where('subject_id', $modelId);
        }
        return $query;
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('logged_at', '>=', now()->subDays($days));
    }

    /**
     * Get log type options
     */
    public static function getLogTypeOptions(): array
    {
        return [
            'activity' => 'Activity',
            'audit' => 'Audit',
            'error' => 'Error',
            'system' => 'System',
        ];
    }

    /**
     * Get level options
     */
    public static function getLevelOptions(): array
    {
        return [
            'debug' => 'Debug',
            'info' => 'Info',
            'warning' => 'Warning',
            'error' => 'Error',
            'critical' => 'Critical',
        ];
    }

    /**
     * Get event options
     */
    public static function getEventOptions(): array
    {
        return [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'viewed' => 'Viewed',
            'restored' => 'Restored',
            'login' => 'Login',
            'logout' => 'Logout',
            'exported' => 'Exported',
            'imported' => 'Imported',
        ];
    }
}
