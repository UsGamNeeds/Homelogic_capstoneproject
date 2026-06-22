<?php

namespace Tests\Unit;

use App\Services\BackupCipher;
use Tests\TestCase;

class BackupCipherTest extends TestCase
{
    private function cipher(): BackupCipher
    {
        config(['backup.encrypt' => true, 'backup.encryption_key' => null]);

        return new BackupCipher;
    }

    public function test_round_trips_a_string(): void
    {
        $cipher = $this->cipher();
        $plain = "-- HL360_FACILITY_BACKUP facility_id=7\nINSERT INTO `residents` VALUES (1,'Jane');";

        $encrypted = $cipher->encryptString($plain);

        $this->assertNotSame($plain, $encrypted);
        $this->assertTrue($cipher->isEncrypted($encrypted));
        $this->assertStringNotContainsString('INSERT INTO', $encrypted, 'ciphertext must not leak plaintext');
        $this->assertStringNotContainsString('Jane', $encrypted);
        $this->assertSame($plain, $cipher->decryptString($encrypted));
    }

    public function test_legacy_plaintext_passes_through_decrypt_unchanged(): void
    {
        $cipher = $this->cipher();
        $legacy = "-- HL360_FACILITY_BACKUP facility_id=3\nINSERT INTO `users` VALUES (1);";

        $this->assertFalse($cipher->isEncrypted($legacy));
        $this->assertSame($legacy, $cipher->decryptString($legacy));
    }

    public function test_encrypt_file_in_place_then_read_decrypted(): void
    {
        $cipher = $this->cipher();
        $path = sys_get_temp_dir().'/bc-test-'.uniqid().'.sql';
        $plain = "-- HL360_FACILITY_BACKUP facility_id=9\nINSERT INTO `vital_signs` VALUES (1,2,3);";
        file_put_contents($path, $plain);

        $cipher->encryptFileInPlace($path);

        $onDisk = file_get_contents($path);
        $this->assertTrue($cipher->isEncrypted($onDisk), 'file on disk must be encrypted');
        $this->assertStringNotContainsString('vital_signs', $onDisk);
        $this->assertSame($plain, $cipher->readDecrypted($path));

        @unlink($path);
    }

    public function test_encrypt_file_in_place_is_idempotent(): void
    {
        $cipher = $this->cipher();
        $path = sys_get_temp_dir().'/bc-test-'.uniqid().'.sql';
        $plain = 'INSERT INTO `x` VALUES (1);';
        file_put_contents($path, $plain);

        $cipher->encryptFileInPlace($path);
        $firstCipher = file_get_contents($path);
        $cipher->encryptFileInPlace($path); // must not double-encrypt

        $this->assertSame($firstCipher, file_get_contents($path));
        $this->assertSame($plain, $cipher->readDecrypted($path));

        @unlink($path);
    }

    public function test_decrypt_to_temp_yields_plaintext_file_for_encrypted_input(): void
    {
        $cipher = $this->cipher();
        $path = sys_get_temp_dir().'/bc-test-'.uniqid().'.sql';
        $plain = "-- HL360_FACILITY_BACKUP facility_id=4\nINSERT INTO `meds` VALUES (1);";
        file_put_contents($path, $plain);
        $cipher->encryptFileInPlace($path);

        $temp = $cipher->decryptToTemp($path);

        $this->assertNotNull($temp);
        $this->assertNotSame($path, $temp);
        $this->assertSame($plain, file_get_contents($temp));

        @unlink($path);
        @unlink($temp);
    }

    public function test_decrypt_to_temp_returns_null_for_legacy_plaintext(): void
    {
        $cipher = $this->cipher();
        $path = sys_get_temp_dir().'/bc-test-'.uniqid().'.sql';
        file_put_contents($path, 'INSERT INTO `x` VALUES (1);');

        $this->assertNull($cipher->decryptToTemp($path), 'legacy plaintext needs no temp; caller uses original');

        @unlink($path);
    }

    public function test_encryption_disabled_writes_plaintext(): void
    {
        config(['backup.encrypt' => false, 'backup.encryption_key' => null]);
        $cipher = new BackupCipher;
        $path = sys_get_temp_dir().'/bc-test-'.uniqid().'.sql';
        $plain = 'INSERT INTO `x` VALUES (1);';
        file_put_contents($path, $plain);

        $cipher->encryptFileInPlace($path);

        $this->assertSame($plain, file_get_contents($path), 'with encryption disabled the file stays plaintext');
        @unlink($path);
    }
}
