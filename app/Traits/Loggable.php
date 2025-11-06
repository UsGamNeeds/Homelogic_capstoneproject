<?php

namespace App\Traits;

use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    /**
     * Boot the trait
     */
    protected static function bootLoggable(): void
    {
        static::created(function ($model) {
            if (static::shouldLog($model, 'created')) {
                ActivityLogService::created($model);
            }
        });

        static::updated(function ($model) {
            if (static::shouldLog($model, 'updated')) {
                $oldAttributes = $model->getOriginal();
                ActivityLogService::updated($model, $oldAttributes);
            }
        });

        static::deleted(function ($model) {
            if (static::shouldLog($model, 'deleted')) {
                ActivityLogService::deleted($model);
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if (static::shouldLog($model, 'restored')) {
                    ActivityLogService::log(
                        event: 'restored',
                        description: 'Restored ' . class_basename($model),
                        subject: $model,
                        logType: 'audit'
                    );
                }
            });
        }
    }

    /**
     * Determine if the model event should be logged
     */
    protected static function shouldLog($model, string $event): bool
    {
        // Don't log if user is not authenticated (e.g., seeder, console command)
        if (!Auth::check()) {
            return false;
        }

        // Check if logging is disabled for this model
        if (isset($model->disableLogging) && $model->disableLogging) {
            return false;
        }

        // Check if specific events are excluded
        if (property_exists(static::class, 'logEvents')) {
            return in_array($event, static::$logEvents);
        }

        // Check if specific events should be excluded
        if (property_exists(static::class, 'logExcept')) {
            return !in_array($event, static::$logExcept);
        }

        return true;
    }

    /**
     * Get activity logs for this model
     */
    public function activityLogs()
    {
        return $this->morphMany(\App\Models\ActivityLog::class, 'subject');
    }

    /**
     * Log a custom activity for this model
     */
    public function logActivity(string $event, string $description, array $properties = [])
    {
        return ActivityLogService::activity($event, $description, $this, $properties);
    }

    /**
     * Log a view for this model
     */
    public function logView(array $properties = [])
    {
        return ActivityLogService::viewed($this, $properties);
    }
}


