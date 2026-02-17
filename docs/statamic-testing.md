# Statamic 6 — Testing in Addons (official docs)

Source: https://github.com/statamic/docs/blob/6.x/content/collections/pages/testing.md

## Scaffolded structure

```
tests/
    ExampleTest.php
    TestCase.php
phpunit.xml
```

## The TestCase

Extends `Statamic\Testing\AddonTestCase` which:
- Boots addon's service provider
- Under the hood extends Orchestra Testbench's `TestCase` (tests against real Laravel app)

### Custom config for tests

```php
protected function resolveApplicationConfiguration($app)
{
    parent::resolveApplicationConfiguration($app);

    $app['config']->set('statamic.editions.pro', true);

    $app['config']->set('statamic.api.resources', [
        'collections' => true,
        'navs' => true,
        'taxonomies' => true,
        'assets' => true,
        'globals' => true,
        'forms' => true,
        'users' => true,
    ]);
}
```

## Writing Tests

All tests extend addon's `TestCase`:

```php
<?php

namespace Acme\Example\Tests;

use Acme\Example\Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
```

### The Stache

Stache items (entries, terms, global sets) saved to `tests/__fixtures__/` during tests.

To prevent saving between runs:

```php
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ExampleTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;
}
```

## Running Tests

```bash
./vendor/bin/phpunit

# Filter by class
./vendor/bin/phpunit --filter=CheckoutTest

# Filter by method
./vendor/bin/phpunit --filter=user_cant_checkout_without_payment

# Filter by keyword
./vendor/bin/phpunit --filter=checkout
```

## GitHub Actions

Example workflow for CI:

```yaml
name: Test Suite

on:
  push:
  pull_request:

jobs:
  php_tests:
    strategy:
      matrix:
        php: [8.2, 8.3]
        laravel: [10.*, 11.*]
        os: [ubuntu-latest]

    name: ${{ matrix.php }} - ${{ matrix.laravel }}
    runs-on: ${{ matrix.os }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v1

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, imagick

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
          composer install --no-interaction

      - name: Run PHPUnit
        run: vendor/bin/phpunit
```
