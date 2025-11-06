<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log an activity
     */
    public static function log(
        string $event,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = [],
        array $context = [],
        string $logType = 'activity',
        string $level = 'info'
    ): ActivityLog {
        $user = Auth::user();
        $branchId = null;

        // Try to get branch ID from various sources
        if ($user && $user->assigned_branch_id) {
            $branchId = $user->assigned_branch_id;
        } elseif ($subject && method_exists($subject, 'branch_id')) {
            $branchId = $subject->branch_id;
        } elseif ($subject && method_exists($subject, 'branch')) {
            $branch = $subject->branch;
            $branchId = $branch?->id;
        } elseif (isset($properties['branch_id'])) {
            $branchId = $properties['branch_id'];
        }

        // Auto-generate description if not provided
        if (!$description && $subject) {
            $subjectName = class_basename($subject);
            $description = ucfirst($event) . ' ' . $subjectName;
            if ($subject->getKey()) {
                $description .= ' #' . $subject->getKey();
            }
            if (method_exists($subject, 'name')) {
                $description .= ' (' . $subject->name . ')';
            }
        }

        // Merge request context
        $mergedContext = array_merge([
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ], $context);

        return ActivityLog::create([
            'log_type' => $logType,
            'event' => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
            'context' => $mergedContext,
            'user_id' => $user?->id,
            'branch_id' => $branchId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'level' => $level,
            'logged_at' => now(),
        ]);
    }

    /**
     * Log a created event
     */
    public static function created(Model $model, array $properties = [], ?string $description = null): ActivityLog
    {
        return self::log(
            event: 'created',
            description: $description,
            subject: $model,
            properties: array_merge(['attributes' => $model->getAttributes()], $properties),
            logType: 'audit'
        );
    }

    /**
     * Log an updated event
     */
    public static function updated(Model $model, array $oldAttributes = [], array $properties = [], ?string $description = null): ActivityLog
    {
        $changed = [];
        foreach ($model->getChanges() as $key => $value) {
            $changed[$key] = [
                'old' => $oldAttributes[$key] ?? $model->getOriginal($key),
                'new' => $value,
            ];
        }

        return self::log(
            event: 'updated',
            description: $description,
            subject: $model,
            properties: array_merge(['changes' => $changed], $properties),
            logType: 'audit'
        );
    }

    /**
     * Log a deleted event
     */
    public static function deleted(Model $model, array $properties = [], ?string $description = null): ActivityLog
    {
        return self::log(
            event: 'deleted',
            description: $description,
            subject: $model,
            properties: array_merge(['attributes' => $model->getAttributes()], $properties),
            logType: 'audit'
        );
    }

    /**
     * Log a viewed event
     */
    public static function viewed(Model $model, array $properties = [], ?string $description = null): ActivityLog
    {
        return self::log(
            event: 'viewed',
            description: $description,
            subject: $model,
            properties: $properties
        );
    }

    /**
     * Log a user login
     */
    public static function login(\App\Models\User $user, array $properties = []): ActivityLog
    {
        return self::log(
            event: 'login',
            description: "User {$user->name} logged in",
            subject: $user,
            properties: $properties,
            logType: 'system'
        );
    }

    /**
     * Log a user logout
     */
    public static function logout(\App\Models\User $user, array $properties = []): ActivityLog
    {
        return self::log(
            event: 'logout',
            description: "User {$user->name} logged out",
            subject: $user,
            properties: $properties,
            logType: 'system'
        );
    }

    /**
     * Log an error
     */
    public static function error(string $description, ?Model $subject = null, array $properties = [], array $context = []): ActivityLog
    {
        return self::log(
            event: 'error',
            description: $description,
            subject: $subject,
            properties: $properties,
            context: $context,
            logType: 'error',
            level: 'error'
        );
    }

    /**
     * Log a system event
     */
    public static function system(string $event, string $description, array $properties = [], string $level = 'info'): ActivityLog
    {
        return self::log(
            event: $event,
            description: $description,
            properties: $properties,
            logType: 'system',
            level: $level
        );
    }

    /**
     * Log a custom activity
     */
    public static function activity(string $event, string $description, ?Model $subject = null, array $properties = []): ActivityLog
    {
        return self::log(
            event: $event,
            description: $description,
            subject: $subject,
            properties: $properties,
            logType: 'activity'
        );
    }
}

