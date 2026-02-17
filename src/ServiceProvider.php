<?php

namespace Isapp\SensitiveFormFields;

use Illuminate\Support\Facades\Event;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Listeners\EncryptSensitiveFields;
use Isapp\SensitiveFormFields\Repositories\DecryptingSubmissionRepository;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Events\SubmissionSaving;
use Statamic\Facades\Permission;
use Statamic\Fields\Fieldtype;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(FieldEncryptor::class);
        $this->app->singleton(SensitiveFieldResolver::class);
    }

    public function bootAddon()
    {
        $this->registerPermission();
        $this->appendFieldConfig();
        $this->registerListener();
        $this->decorateRepository();
    }

    protected function registerPermission(): void
    {
        Permission::extend(function () {
            Permission::register('view decrypted sensitive fields')
                ->label('View Decrypted Sensitive Fields')
                ->description('Allow viewing decrypted values of sensitive form fields');
        });
    }

    protected function appendFieldConfig(): void
    {
        Fieldtype::appendConfigField('sensitive', [
            'type' => 'toggle',
            'display' => 'Sensitive (encrypted at rest)',
            'instructions' => 'When enabled, this field\'s value will be encrypted before storage.',
            'default' => false,
            'width' => 50,
        ]);
    }

    protected function registerListener(): void
    {
        Event::listen(SubmissionSaving::class, EncryptSensitiveFields::class);
    }

    protected function decorateRepository(): void
    {
        $this->app->extend(SubmissionRepository::class, function (SubmissionRepository $repository, $app) {
            return new DecryptingSubmissionRepository(
                $repository,
                $app->make(FieldEncryptor::class),
                $app->make(SensitiveFieldResolver::class),
            );
        });
    }
}
