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
        $this->registerSettings();

        if (app(FieldEncryptor::class)->isPro()) {
            $this->registerPermission();
        }
        $this->appendFieldConfig();
        $this->decorateRepository();
    }

    protected function registerSettings(): void
    {
        $fields = [
            [
                'handle' => 'enabled',
                'field' => [
                    'type' => 'toggle',
                    'display' => __('statamic-sensitive-form-fields::messages.settings_enabled_display'),
                    'instructions' => __('statamic-sensitive-form-fields::messages.settings_enabled_instructions'),
                    'default' => true,
                    'width' => 50,
                ],
            ],
        ];

        if (app(FieldEncryptor::class)->isPro()) {
            $fields[] = [
                'handle' => 'mask',
                'field' => [
                    'type' => 'text',
                    'display' => __('statamic-sensitive-form-fields::messages.settings_mask_display'),
                    'instructions' => __('statamic-sensitive-form-fields::messages.settings_mask_instructions'),
                    'default' => '••••••',
                    'width' => 50,
                ],
            ];
        }

        $this->registerSettingsBlueprint([
            'tabs' => [
                'main' => [
                    'sections' => [
                        ['fields' => $fields],
                    ],
                ],
            ],
        ]);
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
