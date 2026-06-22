<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

/**
 * Encrypts backup .sql files at rest (they contain PHI for the whole facility).
 *
 * Encrypted files keep their .sql name but begin with a marker so the system
 * can tell encrypted backups from the legacy plaintext ones already on disk:
 * download and restore transparently decrypt ONLY when the marker is present,
 * so existing plaintext backups keep working unchanged.
 *
 * Uses Laravel's authenticated Encrypter (AES-256-CBC + HMAC). A dedicated
 * BACKUP_ENCRYPTION_KEY can be configured; otherwise APP_KEY is used.
 */
class BackupCipher
{
    /** Prefix stamped on encrypted payloads. Never appears at the start of real SQL. */
    private const MARKER = 'HL360ENC1:';

    private bool $enabled;

    private Encrypter $encrypter;

    public function __construct()
    {
        $this->enabled = (bool) config('backup.encrypt', true);
        $this->encrypter = $this->resolveEncrypter();
    }

    private function resolveEncrypter(): Encrypter
    {
        $key = config('backup.encryption_key');

        if (filled($key)) {
            return new Encrypter($this->parseKey((string) $key), 'AES-256-CBC');
        }

        // Default to the application encrypter (APP_KEY).
        return app('encrypter');
    }

    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return (string) base64_decode(substr($key, 7), true);
        }

        return $key;
    }

    public function isEncrypted(string $bytes): bool
    {
        return str_starts_with($bytes, self::MARKER);
    }

    /** Encrypt a string. No-op when disabled or already encrypted. */
    public function encryptString(string $plain): string
    {
        if (! $this->enabled || $this->isEncrypted($plain)) {
            return $plain;
        }

        return self::MARKER.$this->encrypter->encryptString($plain);
    }

    /** Decrypt a string. Returns legacy plaintext unchanged when no marker. */
    public function decryptString(string $bytes): string
    {
        if (! $this->isEncrypted($bytes)) {
            return $bytes;
        }

        return $this->encrypter->decryptString(substr($bytes, strlen(self::MARKER)));
    }

    /** Encrypt a file in place. No-op when disabled, empty, or already encrypted. */
    public function encryptFileInPlace(string $path): void
    {
        if (! $this->enabled) {
            return;
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '' || $this->isEncrypted($bytes)) {
            return;
        }

        file_put_contents($path, self::MARKER.$this->encrypter->encryptString($bytes));
    }

    /** Read a backup file and return its plaintext SQL (decrypting if needed). */
    public function readDecrypted(string $path): string
    {
        return $this->decryptString((string) file_get_contents($path));
    }

    /**
     * If the file is encrypted, write its plaintext to a fresh temp file and
     * return that path (caller must unlink it). Returns null for legacy
     * plaintext files, signalling the caller to use the original path.
     */
    public function decryptToTemp(string $path): ?string
    {
        $bytes = (string) file_get_contents($path);
        if (! $this->isEncrypted($bytes)) {
            return null;
        }

        $plain = $this->encrypter->decryptString(substr($bytes, strlen(self::MARKER)));
        $temp = (string) tempnam(sys_get_temp_dir(), 'hl360-dec-');
        file_put_contents($temp, $plain);

        return $temp;
    }
}
