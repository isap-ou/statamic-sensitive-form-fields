<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Encryption;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Statamic\Addons\Addon;

class FieldEncryptor
{
    // Versioned prefix prepended to every ciphertext.
    // Allows detecting already-encrypted values and future format migrations.
    protected const PREFIX = 'enc:v1:';

    public function __construct(
        protected Addon $addon,
    ) {}

    // Idempotent: returns the value unchanged if it is already encrypted.
    public function encrypt(string $value): string
    {
        if ($this->isEncrypted($value)) {
            return $value;
        }

        return self::PREFIX . Crypt::encryptString($value);
    }

    // Returns the value unchanged (with a log warning) on decryption failure,
    // e.g. after an APP_KEY rotation. Callers should treat the raw ciphertext
    // as an opaque fallback rather than a fatal error.
    public function decrypt(string $value): string
    {
        if (! $this->isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, \strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            Log::warning('Failed to decrypt sensitive field value: ' . $e->getMessage());

            return $value;
        }
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->addon->setting('enabled') ?? true);
    }

    public function mask(): string
    {
        return (string) ($this->addon->setting('mask') ?? '••••••');
    }
}
