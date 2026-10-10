<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule notifications to be generated every 15 minutes (medication-due summaries need the finer cadence)
Schedule::command('notifications:generate')->everyFifteenMinutes()->withoutOverlapping(10);

// Reminders
Schedule::command('reminders:generate')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('reminders:dispatch')->everyFiveMinutes()->withoutOverlapping();

// Medications - Mark missed medications:
// 1. Real-time: Every 5 minutes to catch missed windows shortly after they close
Schedule::command('medications:mark-missed')->everyFiveMinutes()->withoutOverlapping();
// 2. End-of-day: Daily at 11:55 PM to catch any missed doses from the day
Schedule::command('medications:mark-missed --end-of-day')->dailyAt('23:55')->withoutOverlapping();

// Medications - Pre-window opening emails removed (too noisy). Admins are emailed when a dose is
// missed after the window closes — see medications:mark-missed + NotificationService::sendMissedMedicationWindowAdminEmail.

// Notifications cleanup: delete read > 30 days, all > 90 days
Schedule::command('notifications:cleanup')->dailyAt('03:00');

// Database: automatic daily backup to storage/app/backups (backup_auto_*.sql). Requires server cron running `schedule:run`.
Schedule::command('database:backup --scheduled')
    ->dailyAt(config('backup.scheduled_time', '02:00'))
    ->when(fn () => (bool) config('backup.scheduled_enabled', true))
    ->withoutOverlapping(120);

// Fax: delete PDFs + rows past each facility's retention window.
Schedule::command('fax:purge')->dailyAt('03:30')->withoutOverlapping();

// Backups: keep only the newest N (config backup.scheduled_keep) auto backups
// per facility as a storage safety net. Manual snapshots are preserved.
Schedule::command('backups:prune')->dailyAt('03:45')->withoutOverlapping();
