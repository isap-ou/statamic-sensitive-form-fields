<?php

namespace Isapp\SensitiveFormFields\Tests\Feature;

use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Tests\TestCase;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Form;
use Statamic\Facades\FormSubmission;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class SensitiveFieldsTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

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

    protected function createSubmission(array $data = []): \Statamic\Contracts\Forms\Submission
    {
        $form = Form::find('contact');

        $submission = FormSubmission::make()->form($form)->data(array_merge([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Hello!',
        ], $data));

        // Use $submission->save() to trigger SubmissionSaving event
        $submission->save();

        return $submission;
    }

    public function test_sensitive_field_is_stored_encrypted()
    {
        $submission = $this->createSubmission();

        // After save, the submission data has been mutated by the listener
        $this->assertStringStartsWith('enc:v1:', $submission->get('email'));
        $this->assertStringStartsWith('enc:v1:', $submission->get('message'));
    }

    public function test_non_sensitive_field_remains_plain()
    {
        $submission = $this->createSubmission();

        $this->assertSame('John Doe', $submission->get('name'));
    }

    public function test_authorized_user_reads_plaintext()
    {
        $submission = $this->createSubmission();

        $user = User::make()->makeSuper();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('john@example.com', $found->get('email'));
        $this->assertSame('Hello!', $found->get('message'));
        $this->assertSame('John Doe', $found->get('name'));
    }

    public function test_unauthorized_user_reads_masked_value()
    {
        $submission = $this->createSubmission();

        $user = User::make()->id('test-user')->email('test@test.com');
        $user->save();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('••••••', $found->get('email'));
        $this->assertSame('••••••', $found->get('message'));
        $this->assertSame('John Doe', $found->get('name'));
    }

    public function test_user_with_permission_reads_plaintext()
    {
        $submission = $this->createSubmission();

        $role = Role::make()->handle('sensitive-reader')
            ->title('Sensitive Reader')
            ->permissions(['view decrypted sensitive fields']);
        $role->save();

        $user = User::make()->id('reader-user')->email('reader@test.com');
        $user->assignRole('sensitive-reader');
        $user->save();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('john@example.com', $found->get('email'));
        $this->assertSame('Hello!', $found->get('message'));
    }

    public function test_already_encrypted_value_is_not_double_encrypted()
    {
        $encryptor = app(FieldEncryptor::class);
        $preEncrypted = $encryptor->encrypt('john@example.com');

        $submission = $this->createSubmission(['email' => $preEncrypted]);

        // Should be the same encrypted value, not double-encrypted
        $this->assertSame($preEncrypted, $submission->get('email'));

        // And it should still decrypt correctly
        $this->assertSame('john@example.com', $encryptor->decrypt($submission->get('email')));
    }
}
