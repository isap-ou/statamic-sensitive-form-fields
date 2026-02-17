<?php

declare(strict_types=1);

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

        $submission->save();

        return $submission;
    }

    protected function enableProMode(): void
    {
        app()->bind('isapp.sensitive-form-fields.pro', fn () => true);
    }

    // --- Encryption on write ---

    public function test_sensitive_field_is_stored_encrypted()
    {
        $submission = $this->createSubmission();

        $this->assertStringStartsWith('enc:v1:', $submission->get('email'));
        $this->assertStringStartsWith('enc:v1:', $submission->get('message'));
    }

    public function test_non_sensitive_field_remains_plain()
    {
        $submission = $this->createSubmission();

        $this->assertSame('John Doe', $submission->get('name'));
    }

    public function test_already_encrypted_value_is_not_double_encrypted()
    {
        $encryptor = app(FieldEncryptor::class);
        $preEncrypted = $encryptor->encrypt('john@example.com');

        $submission = $this->createSubmission(['email' => $preEncrypted]);

        $this->assertSame($preEncrypted, $submission->get('email'));
        $this->assertSame('john@example.com', $encryptor->decrypt($submission->get('email')));
    }

    // --- FREE mode (default): all users see decrypted ---

    public function test_free_mode_all_users_see_decrypted_value()
    {
        $submission = $this->createSubmission();

        $user = User::make()->id('free-user')->email('free@test.com');
        $user->save();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('john@example.com', $found->get('email'));
        $this->assertSame('Hello!', $found->get('message'));
    }

    public function test_free_mode_super_admin_sees_decrypted_value()
    {
        $submission = $this->createSubmission();

        $user = User::make()->makeSuper();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('john@example.com', $found->get('email'));
        $this->assertSame('Hello!', $found->get('message'));
        $this->assertSame('John Doe', $found->get('name'));
    }

    // --- PRO mode: permission-based access control ---

    public function test_pro_mode_super_admin_reads_plaintext()
    {
        $this->enableProMode();
        $submission = $this->createSubmission();

        $user = User::make()->makeSuper();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('john@example.com', $found->get('email'));
        $this->assertSame('Hello!', $found->get('message'));
        $this->assertSame('John Doe', $found->get('name'));
    }

    public function test_pro_mode_unauthorized_user_reads_masked_value()
    {
        $this->enableProMode();
        $submission = $this->createSubmission();

        $user = User::make()->id('test-user')->email('test@test.com');
        $user->save();
        $this->actingAs($user);

        $found = FormSubmission::find($submission->id());

        $this->assertSame('••••••', $found->get('email'));
        $this->assertSame('••••••', $found->get('message'));
        $this->assertSame('John Doe', $found->get('name'));
    }

    public function test_pro_mode_user_with_permission_reads_plaintext()
    {
        $this->enableProMode();
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

    // --- Query builder path (CP submissions list) ---

    public function test_query_builder_decrypts_for_super_admin_in_free_mode()
    {
        $this->createSubmission();

        $user = User::make()->makeSuper();
        $this->actingAs($user);

        $results = FormSubmission::query()->where('form', 'contact')->get();

        $this->assertSame('john@example.com', $results->first()->get('email'));
    }

    public function test_query_builder_masks_for_unauthorized_user_in_pro_mode()
    {
        $this->enableProMode();
        $this->createSubmission();

        $user = User::make()->id('qb-user')->email('qb@test.com');
        $user->save();
        $this->actingAs($user);

        $results = FormSubmission::query()->where('form', 'contact')->get();

        $this->assertSame('••••••', $results->first()->get('email'));
    }
}
