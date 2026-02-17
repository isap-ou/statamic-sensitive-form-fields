<?php

declare(strict_types=1);

namespace Isapp\SensitiveFormFields\Tests;

use Isapp\SensitiveFormFields\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
