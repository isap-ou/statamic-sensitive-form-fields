<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests;

use Isapp\SensitiveFormFields\ServiceProvider;
use Statamic\Addons\Manifest;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // AddonTestCase builds the manifest without the `editions` key.
        // Populate it so that Addon::edition() works correctly in tests.
        $manifest = $app->make(Manifest::class);
        $manifest->manifest['isapp/statamic-sensitive-form-fields']['editions'] = ['free', 'pro'];
    }
}
