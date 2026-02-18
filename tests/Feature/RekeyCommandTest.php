<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests\Feature;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Isapp\SensitiveFormFields\Repositories\RawSubmissionRepository;
use Isapp\SensitiveFormFields\Tests\TestCase;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Facades\FormSubmission;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class RekeyCommandTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // PRO mode must be active at boot time so that registerCommands() fires
        // inside the isPro() branch of ServiceProvider::bootAddon().
        $app['config']->set('statamic.editions.addons.isapp/statamic-sensitive-form-fields', 'pro');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContactForm();
    }

    protected function createContactForm(): void
    {
        $blueprint = Blueprint::makeFromFields([
            'name' => [
                'type' => 'text',
                'display' => 'Name',
            ],
            'email' => [
                'type' => 'text',
                'display' => 'Email',
                'sensitive' => true,
            ],
            'message' => [
                'type' => 'textarea',
                'display' => 'Message',
                'sensitive' => true,
            ],
        ]);

        $blueprint->setHandle('contact');
        $blueprint->setNamespace('forms');
        $blueprint->save();

        $form = Form::make('contact')->title('Contact');
        $form->save();
    }

    /**
     * Returns the raw (undecorated) SubmissionRepository.
     * Resolving SubmissionRepository first triggers the extend callback,
     * which binds RawSubmissionRepository.
     */
    protected function getRawRepository(): SubmissionRepository
    {
        app(SubmissionRepository::class);

        return app(RawSubmissionRepository::class);
    }

    /**
     * Creates a submission with values pre-encrypted using the given Encrypter,
     * bypassing the SubmissionSaving listener so values are stored as-is.
     */
    protected function createSubmissionEncryptedWith(Encrypter $encrypter): \Statamic\Contracts\Forms\Submission
    {
        $rawRepo = $this->getRawRepository();
        $form = Form::find('contact');

        $submission = FormSubmission::make()->form($form)->data([
            'name' => 'John Doe',
            'email' => 'enc:v1:' . $encrypter->encryptString('john@example.com'),
            'message' => 'enc:v1:' . $encrypter->encryptString('Hello!'),
        ]);

        $rawRepo->save($submission);

        return $submission;
    }

    // --- rekey ---

    public function test_rekey_re_encrypts_with_current_key(): void
    {
        $rawOldKey = Encrypter::generateKey('AES-256-CBC');
        $oldEncrypter = new Encrypter($rawOldKey, 'AES-256-CBC');
        $oldKeyOption = 'base64:' . base64_encode($rawOldKey);

        $submission = $this->createSubmissionEncryptedWith($oldEncrypter);
        $id = $submission->id();

        $this->artisan('sensitive-fields:rekey', ['--old-key' => $oldKeyOption])->assertExitCode(0);

        $raw = $this->getRawRepository()->find($id);

        // Must still have enc:v1: prefix.
        $this->assertStringStartsWith('enc:v1:', $raw->get('email'));
        $this->assertStringStartsWith('enc:v1:', $raw->get('message'));

        // Must now decrypt correctly with the current APP_KEY.
        $this->assertSame('john@example.com', Crypt::decryptString(substr($raw->get('email'), \strlen('enc:v1:'))));
        $this->assertSame('Hello!', Crypt::decryptString(substr($raw->get('message'), \strlen('enc:v1:'))));

        // Non-sensitive field must be unchanged.
        $this->assertSame('John Doe', $raw->get('name'));
    }

    public function test_rekey_skips_plaintext_sensitive_values(): void
    {
        $rawOldKey = Encrypter::generateKey('AES-256-CBC');
        $oldKeyOption = 'base64:' . base64_encode($rawOldKey);

        $rawRepo = $this->getRawRepository();
        $form = Form::find('contact');

        $submission = FormSubmission::make()->form($form)->data([
            'name' => 'John Doe',
            'email' => 'john@example.com',   // plaintext, not encrypted
            'message' => 'Hello!',
        ]);
        $rawRepo->save($submission);
        $id = $submission->id();

        $this->artisan('sensitive-fields:rekey', ['--old-key' => $oldKeyOption])->assertExitCode(0);

        // Plaintext values must remain unchanged.
        $raw = $rawRepo->find($id);
        $this->assertSame('john@example.com', $raw->get('email'));
        $this->assertSame('Hello!', $raw->get('message'));
    }

    public function test_rekey_dry_run_does_not_persist(): void
    {
        $rawOldKey = Encrypter::generateKey('AES-256-CBC');
        $oldEncrypter = new Encrypter($rawOldKey, 'AES-256-CBC');
        $oldKeyOption = 'base64:' . base64_encode($rawOldKey);

        $submission = $this->createSubmissionEncryptedWith($oldEncrypter);
        $id = $submission->id();
        $originalCiphertext = $this->getRawRepository()->find($id)->get('email');

        $this->artisan('sensitive-fields:rekey', ['--old-key' => $oldKeyOption, '--dry-run' => true])->assertExitCode(0);

        // Value must still be the original ciphertext (old key, not re-encrypted).
        $this->assertSame($originalCiphertext, $this->getRawRepository()->find($id)->get('email'));
    }

    public function test_rekey_fails_without_old_key(): void
    {
        $this->artisan('sensitive-fields:rekey')->assertExitCode(1);
    }

    public function test_rekey_fails_with_invalid_old_key(): void
    {
        // Valid base64 but wrong key length for AES-256-CBC (needs 32 bytes).
        $shortKey = 'base64:' . base64_encode('tooshort');

        $this->artisan('sensitive-fields:rekey', ['--old-key' => $shortKey])->assertExitCode(1);
    }

    public function test_rekey_reports_error_when_old_key_cannot_decrypt(): void
    {
        $actualOldKey = Encrypter::generateKey('AES-256-CBC');
        $wrongOldKey = Encrypter::generateKey('AES-256-CBC');

        $actualEncrypter = new Encrypter($actualOldKey, 'AES-256-CBC');
        $wrongKeyOption = 'base64:' . base64_encode($wrongOldKey);

        $submission = $this->createSubmissionEncryptedWith($actualEncrypter);
        $id = $submission->id();
        $originalCiphertext = $this->getRawRepository()->find($id)->get('email');

        // Running with the wrong key — decryption should fail and value must not be overwritten.
        $this->artisan('sensitive-fields:rekey', ['--old-key' => $wrongKeyOption])->assertExitCode(0);

        $raw = $this->getRawRepository()->find($id);
        $this->assertSame($originalCiphertext, $raw->get('email'));
    }
}
