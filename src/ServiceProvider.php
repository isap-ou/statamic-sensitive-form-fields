<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields;

use Isapp\SensitiveFormFields\Commands\DecryptExistingCommand;
use Isapp\SensitiveFormFields\Commands\EncryptExistingCommand;
use Isapp\SensitiveFormFields\Commands\RekeyCommand;
use Isapp\SensitiveFormFields\Encryption\FieldEncryptor;
use Isapp\SensitiveFormFields\Repositories\DecryptingSubmissionRepository;
use Isapp\SensitiveFormFields\Repositories\RawSubmissionRepository;
use Isapp\SensitiveFormFields\Support\SensitiveFieldResolver;
use Statamic\Contracts\Forms\SubmissionRepository;
use Statamic\Facades\Form;
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
        $isPro = $this->getAddon()->edition() === 'pro';

        $this->registerSettings($isPro);

        if ($isPro) {
            $this->registerPermission();
            $this->registerCommands();
        }

        $this->appendFieldConfig();
        $this->decorateRepository();
    }

    protected function registerSettings(bool $isPro): void
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

        if ($isPro) {
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

    private function registerPermission(): void
    {
        Permission::extend(function () {
            Permission::register('view decrypted sensitive fields')
                ->label(__('statamic-sensitive-form-fields::messages.permission_label'))
                ->description(__('statamic-sensitive-form-fields::messages.permission_description'));

            Permission::register('view decrypted {form} sensitive fields')
                ->label(__('statamic-sensitive-form-fields::messages.permission_form_label'))
                ->description(__('statamic-sensitive-form-fields::messages.permission_form_description'))
                ->replacements('form', fn () => Form::all()->map(fn ($f) => [
                    'value' => $f->handle(),
                    'label' => $f->title(),
                ]));
        });
    }

    protected function appendFieldConfig(): void
    {
        // Scoped to Text and Textarea only — the fieldtypes used in form blueprints.
        // Intentionally not registered on the global Fieldtype base to avoid
        // showing the toggle on content entry fields.
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
        // extend() is lazy: the callback runs the first time SubmissionRepository
        // is resolved from the container, not at boot time.
        $this->app->extend(SubmissionRepository::class, function (SubmissionRepository $repository, $app) {
            // Expose the original repository under a typed key so PRO commands
            // can read and write raw (unprocessed) submission data, bypassing
            // the decryption decorator and the SubmissionSaving event.
            $app->instance(RawSubmissionRepository::class, $repository);

            return new DecryptingSubmissionRepository(
                $repository,
                $app->make(FieldEncryptor::class),
                $app->make(SensitiveFieldResolver::class),
                $this->getAddon(),
            );
        });
    }

    private function registerCommands(): void
    {
        $this->commands([
            EncryptExistingCommand::class,
            DecryptExistingCommand::class,
            RekeyCommand::class,
        ]);
    }
}
