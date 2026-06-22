<?php

namespace Tests\Feature;

use App\Services\BackupCipher;
use App\Services\FacilitySqlExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupFacility;

/**
 * Batch 4b — facility backups must be encrypted at rest (they contain a whole
 * facility's PHI). New exports are encrypted; legacy plaintext backups still
 * read back unchanged.
 */
class FacilityBackupEncryptionTest extends TestCase
{
    use RefreshDatabase;
    use SetupFacility;

    public function test_facility_export_is_encrypted_on_disk(): void
    {
        config(['backup.encrypt' => true, 'backup.encryption_key' => null]);

        [$facility, $branch] = $this->createFacilityAndBranch();
        $resident = $this->createResident($branch);

        $result = app(FacilitySqlExportService::class)->export($facility->id);

        $this->assertTrue($result['success'], $result['message'] ?? 'export failed');
        $path = $result['path'];
        $raw = file_get_contents($path);

        // On disk: encrypted, no plaintext PHI / SQL visible.
        $this->assertTrue(app(BackupCipher::class)->isEncrypted($raw), 'backup file must be encrypted at rest');
        $this->assertStringNotContainsString('INSERT INTO', $raw);
        $this->assertStringNotContainsString('HL360_FACILITY_BACKUP', $raw);
        $this->assertStringNotContainsString($resident->last_name, $raw, 'resident PHI must not be in plaintext');

        // Decrypted: real SQL with the routable header + resident row.
        $plain = app(BackupCipher::class)->readDecrypted($path);
        $this->assertStringContainsString('-- HL360_FACILITY_BACKUP facility_id='.$facility->id, $plain);
        $this->assertStringContainsString('INSERT INTO `residents`', $plain);
        $this->assertStringContainsString($resident->last_name, $plain);

        @unlink($path);
    }

    public function test_export_can_be_disabled_for_debugging(): void
    {
        config(['backup.encrypt' => false, 'backup.encryption_key' => null]);

        [$facility, $branch] = $this->createFacilityAndBranch();

        $result = app(FacilitySqlExportService::class)->export($facility->id);
        $raw = file_get_contents($result['path']);

        $this->assertFalse(app(BackupCipher::class)->isEncrypted($raw));
        $this->assertStringContainsString('HL360_FACILITY_BACKUP', $raw);

        @unlink($result['path']);
    }
}
