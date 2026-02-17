<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields;

use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Repositories\DecryptingSubmissionRepository;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Permission;
use Statamic\Fieldtypes\Text;
use Statamic\Fieldtypes\Textarea;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->singleton(FieldEncryptor::class, function () {
            return new FieldEncryptor($this->getAddon());
        });

        $this->app->singleton(SensitiveFieldResolver::class);
    }

    public function bootAddon()
    {
        if (app(FieldEncryptor::class)->isPro()) {
            $this->registerPermission();
        }
        $this->appendFieldConfig();
        $this->decorateRepository();
    }

    protected function registerPermission(): void
    {
        Permission::extend(function () {
            Permission::register('view decrypted sensitive fields')
                ->label(__('statamic-sensitive-form-fields::messages.permission_label'))
                ->description(__('statamic-sensitive-form-fields::messages.permission_description'));
        });
    }

    protected function appendFieldConfig(): void
    {
        $config = [
            'type' => 'toggle',
            'display' => __('statamic-sensitive-form-fields::messages.field_toggle_display'),
            'instructions' => __('statamic-sensitive-form-fields::messages.field_toggle_instructions'),
            'default' => false,
            'width' => 50,
        ];

        Text::appendConfigField('sensitive', $config);
        Textarea::appendConfigField('sensitive', $config);
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
