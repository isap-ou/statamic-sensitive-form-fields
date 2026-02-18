<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Encryption;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Statamic\Addons\Addon;
use Statamic\Facades\CP\Toast;

class FieldEncryptor
{
    // Versioned prefix prepended to every ciphertext.
    // Allows detecting already-encrypted values and future format migrations.
    protected const PREFIX = 'enc:v1:';

    // Suppress repeated CP toasts for the same form within this window (seconds).
    protected const NOTIFY_TTL = 3600;

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
    //
    // $context is an optional form handle used to deduplicate CP toasts so that
    // bulk failures (e.g. many submissions after an APP_KEY rotation) produce at
    // most one toast per form per hour. Pass the form handle from repository callers;
    // omit from CLI callers (toast is suppressed in console context anyway).
    public function decrypt(string $value, string $context = ''): string
    {
        if (! $this->isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, \strlen(self::PREFIX)));
        } catch (\Throwable $e) {
            Log::warning('Failed to decrypt sensitive field value: ' . $e->getMessage());

            // Only dispatch CP toasts during HTTP (CP) requests.
            // Commands and queue workers run in console context — skip entirely.
            // Cache::add() is atomic set-if-not-exists; the key is rolled back via
            // Cache::forget() when toast delivery fails so the dedup window is not
            // consumed without a toast being shown. Wrapped in try/catch so that
            // cache or session failures never break the graceful fallback.
            try {
                if (! app()->runningInConsole()) {
                    $cacheKey = 'sffields.decrypt_failure_notified.' . ($context ?: 'unknown');
                    if (Cache::add($cacheKey, true, self::NOTIFY_TTL)) {
                        try {
                            Toast::error(__('statamic-sensitive-form-fields::messages.decrypt_failure_toast'));
                        } catch (\Throwable) {
                            // Roll back the dedup key so a future request with an active
                            // CP session can still deliver the toast.
                            Cache::forget($cacheKey);
                        }
                    }
                }
            } catch (\Throwable) {
                // Cache failure must never convert a recoverable decrypt error into a fatal one.
            }

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
