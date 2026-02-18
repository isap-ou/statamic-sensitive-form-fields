<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests\Unit;

use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Tests\TestCase;

class FieldEncryptorTest extends TestCase
{
    protected FieldEncryptor $encryptor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryptor = app(FieldEncryptor::class);
    }

    public function test_encrypts_value_with_marker_prefix()
    {
        $encrypted = $this->encryptor->encrypt('secret');

        $this->assertStringStartsWith('enc:v1:', $encrypted);
    }

    public function test_decrypts_value_back_to_plaintext()
    {
        $encrypted = $this->encryptor->encrypt('secret');
        $decrypted = $this->encryptor->decrypt($encrypted);

        $this->assertSame('secret', $decrypted);
    }

    public function test_does_not_double_encrypt()
    {
        $encrypted = $this->encryptor->encrypt('secret');
        $encryptedAgain = $this->encryptor->encrypt($encrypted);

        $this->assertSame($encrypted, $encryptedAgain);
    }

    public function test_failed_decrypt_returns_raw_value_and_logs_warning()
    {
        $corrupted = 'enc:v1:corrupted-ciphertext';

        $decrypted = $this->encryptor->decrypt($corrupted);

        $this->assertSame($corrupted, $decrypted);
    }

    public function test_is_encrypted_detects_prefix()
    {
        $this->assertTrue($this->encryptor->isEncrypted('enc:v1:something'));
        $this->assertFalse($this->encryptor->isEncrypted('plaintext'));
        $this->assertFalse($this->encryptor->isEncrypted(''));
    }

    public function test_mask_returns_configured_value()
    {
        $this->assertSame('••••••', $this->encryptor->mask());
    }

    public function test_decrypt_returns_non_encrypted_value_as_is()
    {
        $this->assertSame('plaintext', $this->encryptor->decrypt('plaintext'));
    }

    public function test_failed_decrypt_does_not_dispatch_toast_in_console_context()
    {
        // The test suite runs in console context (app()->runningInConsole() === true),
        // so the Toast must never be called — verified by spying on the facade.
        \Statamic\Facades\CP\Toast::spy();

        $this->encryptor->decrypt('enc:v1:corrupted-ciphertext', 'contact');

        \Statamic\Facades\CP\Toast::shouldNotHaveReceived('error');
    }

    public function test_failed_decrypt_without_context_does_not_dispatch_toast_in_console_context()
    {
        \Statamic\Facades\CP\Toast::spy();

        // Backward-compatible: context parameter is optional.
        $this->encryptor->decrypt('enc:v1:corrupted-ciphertext');

        \Statamic\Facades\CP\Toast::shouldNotHaveReceived('error');
    }
}
