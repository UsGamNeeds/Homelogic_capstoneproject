<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * backups:prune keeps the newest N auto backups per facility (and whole-DB),
 * deletes older ones, never touches manual "Backup now" files unless asked,
 * and --dry-run deletes nothing.
 */
class PruneBackupsCommandTest extends TestCase
{
    private string $facilityDir;

    private int $facilityId = 99123;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facilityDir = storage_path('app/backups/facilities/'.$this->facilityId);
        if (! is_dir($this->facilityDir)) {
            mkdir($this->facilityDir, 0755, true);
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        foreach (glob($this->facilityDir.'/*.sql') ?: [] as $f) {
            @unlink($f);
        }
    }

    /** Create $n files matching $prefix with descending age (index 0 = newest). */
    private function makeBackups(string $prefix, int $n): array
    {
        $paths = [];
        for ($i = 0; $i < $n; $i++) {
            $path = $this->facilityDir.'/'.$prefix.$this->facilityId.'_2026-06-'.sprintf('%02d', 20 - $i).'_02-00-00.sql';
            file_put_contents($path, "-- backup $i");
            touch($path, time() - ($i * 86400)); // newest = index 0
            $paths[] = $path;
        }

        return $paths;
    }

    public function test_keeps_newest_n_auto_and_deletes_older(): void
    {
        $auto = $this->makeBackups('backup_auto_facility_', 6);
        $manual = $this->makeBackups('backup_facility_', 2);

        $this->artisan('backups:prune', ['--keep' => 3])->assertExitCode(0);

        // 3 newest auto survive, 3 oldest auto gone.
        $this->assertFileExists($auto[0]);
        $this->assertFileExists($auto[2]);
        $this->assertFileDoesNotExist($auto[3]);
        $this->assertFileDoesNotExist($auto[5]);

        // Manual untouched without --include-manual.
        $this->assertFileExists($manual[0]);
        $this->assertFileExists($manual[1]);
    }

    public function test_include_manual_also_trims_manual(): void
    {
        $manual = $this->makeBackups('backup_facility_', 4);

        $this->artisan('backups:prune', ['--keep' => 1, '--include-manual' => true])->assertExitCode(0);

        $this->assertFileExists($manual[0]);
        $this->assertFileDoesNotExist($manual[1]);
        $this->assertFileDoesNotExist($manual[3]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $auto = $this->makeBackups('backup_auto_facility_', 5);

        $this->artisan('backups:prune', ['--keep' => 1, '--dry-run' => true])->assertExitCode(0);

        foreach ($auto as $path) {
            $this->assertFileExists($path, 'dry-run must not delete anything');
        }
    }
}
