<?php

namespace Isapp\SensitiveFormFields\Encryption;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Addon;

class FieldEncryptor
{
    protected const PREFIX = 'enc:v1:';

    public function encrypt(string $value): string
    {
        if ($this->isEncrypted($value)) {
            return $value;
        }

        return self::PREFIX.Crypt::encryptString($value);
    }

    public function decrypt(string $value): string
    {
        if (! $this->isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            Log::warning('Failed to decrypt sensitive field value: '.$e->getMessage());

            return $value;
        }
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('enabled', true);
    }

    public function mask(): string
    {
        return $this->setting('mask', '••••••');
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        $addon = Addon::get('isapp/statamic-sensitive-form-fields');

        if (! $addon) {
            return $default;
        }

        return $addon->setting($key) ?? $default;
    }
}
