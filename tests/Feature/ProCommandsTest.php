<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests\Feature;

use Isapp\SensitiveFormFields\Repositories\RawSubmissionRepository;
use Isapp\SensitiveFormFields\Tests\TestCase;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Facades\FormSubmission;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ProCommandsTest extends TestCase
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
     * Creates a submission with plaintext sensitive values, bypassing the
     * SubmissionSaving listener so values are NOT encrypted on disk.
     */
    protected function createPlaintextSubmission(array $data = []): \Statamic\Contracts\Forms\Submission
    {
        $rawRepo = $this->getRawRepository();
        $form = Form::find('contact');

        $submission = FormSubmission::make()->form($form)->data(array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello!',
        ], $data));

        $rawRepo->save($submission);

        return $submission;
    }

    /**
     * Creates a submission through the normal save flow so sensitive values
     * ARE encrypted on disk.
     */
    protected function createEncryptedSubmission(array $data = []): \Statamic\Contracts\Forms\Submission
    {
        $form = Form::find('contact');

        $submission = FormSubmission::make()->form($form)->data(array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello!',
        ], $data));

        $submission->save();

        return $submission;
    }

    // --- encrypt-existing ---

    public function test_encrypt_existing_encrypts_plaintext_sensitive_fields()
    {
        $submission = $this->createPlaintextSubmission();
        $id = $submission->id();

        $this->artisan('sensitive-fields:encrypt-existing')->assertExitCode(0);

        $raw = $this->getRawRepository()->find($id);
        $this->assertStringStartsWith('enc:v1:', $raw->get('email'));
        $this->assertStringStartsWith('enc:v1:', $raw->get('message'));
        $this->assertSame('John Doe', $raw->get('name'));
    }

    public function test_encrypt_existing_skips_already_encrypted_fields()
    {
        $submission = $this->createEncryptedSubmission();
        $id = $submission->id();

        $rawRepo = $this->getRawRepository();
        $originalCiphertext = $rawRepo->find($id)->get('email');

        $this->artisan('sensitive-fields:encrypt-existing')->assertExitCode(0);

        // Ciphertext must not change — the value was already encrypted.
        $this->assertSame($originalCiphertext, $rawRepo->find($id)->get('email'));
    }

    public function test_encrypt_existing_dry_run_does_not_persist()
    {
        $submission = $this->createPlaintextSubmission();
        $id = $submission->id();

        $this->artisan('sensitive-fields:encrypt-existing', ['--dry-run' => true])->assertExitCode(0);

        $raw = $this->getRawRepository()->find($id);
        $this->assertSame('john@example.com', $raw->get('email'));
    }

    // --- decrypt-existing ---

    public function test_decrypt_existing_decrypts_encrypted_sensitive_fields()
    {
        $submission = $this->createEncryptedSubmission();
        $id = $submission->id();

        // Verify the value is stored encrypted.
        $raw = $this->getRawRepository()->find($id);
        $this->assertStringStartsWith('enc:v1:', $raw->get('email'));

        $this->artisan('sensitive-fields:decrypt-existing')->assertExitCode(0);

        // After the command, the raw stored value must be plaintext.
        $raw = $this->getRawRepository()->find($id);
        $this->assertSame('john@example.com', $raw->get('email'));
        $this->assertSame('Hello!', $raw->get('message'));
        $this->assertSame('John Doe', $raw->get('name'));
    }

    public function test_decrypt_existing_skips_plaintext_sensitive_fields()
    {
        $submission = $this->createPlaintextSubmission();
        $id = $submission->id();

        $this->artisan('sensitive-fields:decrypt-existing')->assertExitCode(0);

        // Plaintext values must remain unchanged.
        $raw = $this->getRawRepository()->find($id);
        $this->assertSame('john@example.com', $raw->get('email'));
    }

    public function test_decrypt_existing_dry_run_does_not_persist()
    {
        $submission = $this->createEncryptedSubmission();
        $id = $submission->id();

        $rawRepo = $this->getRawRepository();
        $originalCiphertext = $rawRepo->find($id)->get('email');

        $this->artisan('sensitive-fields:decrypt-existing', ['--dry-run' => true])->assertExitCode(0);

        // Value must still be encrypted (dry run, no writes).
        $this->assertSame($originalCiphertext, $rawRepo->find($id)->get('email'));
    }
}
