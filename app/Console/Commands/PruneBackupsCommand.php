<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Keeps storage bounded by retaining only the newest N backups per facility
 * (and N whole-database dumps), deleting older ones. Auto backups already
 * self-prune after each scheduled run; this command cleans the backlog on
 * demand and runs on the scheduler as a safety net.
 *
 * Manual "Backup now" files are preserved by default (they are intentional
 * snapshots); pass --include-manual to trim those too.
 */
class PruneBackupsCommand extends Command
{
    protected $signature = 'backups:prune
        {--keep= : Newest backups to keep per facility (default: config backup.scheduled_keep)}
        {--include-manual : Also trim manual "Backup now" files}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune old database backups, keeping the newest N per facility.';

    public function handle(): int
    {
        $keep = max(1, (int) ($this->option('keep') ?: config('backup.scheduled_keep', 7)));
        $dryRun = (bool) $this->option('dry-run');
        $includeManual = (bool) $this->option('include-manual');

        $root = storage_path('app/backups');
        $deleted = 0;
        $freed = 0;

        // Per-facility folders: storage/app/backups/facilities/<id>/
        foreach (glob($root.'/facilities/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $fid = basename($dir);
            if (! ctype_digit($fid)) {
                continue;
            }

            $deleted += $this->pruneGroup(glob($dir.'/backup_auto_facility_'.$fid.'_*.sql') ?: [], $keep, $dryRun, $freed);

            if ($includeManual) {
                $manual = array_filter(
                    glob($dir.'/backup_facility_'.$fid.'_*.sql') ?: [],
                    fn ($f) => ! str_starts_with(basename($f), 'backup_auto_'),
                );
                $deleted += $this->pruneGroup(array_values($manual), $keep, $dryRun, $freed);
            }
        }

        // Whole-database dumps in the backups root.
        $deleted += $this->pruneGroup(glob($root.'/backup_auto_*.sql') ?: [], $keep, $dryRun, $freed);

        if ($includeManual) {
            $manualFull = array_filter(
                glob($root.'/backup_*.sql') ?: [],
                fn ($f) => ! str_starts_with(basename($f), 'backup_auto_'),
            );
            $deleted += $this->pruneGroup(array_values($manualFull), $keep, $dryRun, $freed);
        }

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $this->info("{$verb} {$deleted} old backup file(s), freeing ".$this->formatBytes($freed).". Keeping newest {$keep} per facility.");

        return self::SUCCESS;
    }

    /**
     * Delete all but the newest $keep files in $files (by mtime).
     *
     * @param  array<int, string>  $files
     */
    private function pruneGroup(array $files, int $keep, bool $dryRun, int &$freed): int
    {
        if (count($files) <= $keep) {
            return 0;
        }

        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a)); // newest first

        $deleted = 0;
        foreach (array_slice($files, $keep) as $path) {
            if (! is_file($path)) {
                continue;
            }
            $freed += (int) filesize($path);
            if ($dryRun) {
                $this->line('  would delete: '.basename($path));
                $deleted++;

                continue;
            }
            if (@unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = $bytes;
        while ($value > 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }
}
