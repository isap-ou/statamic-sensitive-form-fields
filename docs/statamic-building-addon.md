# Statamic 6 — Building an Addon (official docs)

Source: https://github.com/statamic/docs/blob/6.x/content/collections/pages/building-an-addon.md

## Creating an Addon

```shell
php please make:addon example/my-addon
```

Scaffolds a private addon in `addons/` directory.

### Minimal structure

```
addons/vendor/package/
    src/
        ServiceProvider.php
    composer.json
```

### composer.json

```json
{
    "name": "acme/example",
    "description": "Example Addon",
    "autoload": {
        "psr-4": {
            "Acme\\Example\\": "src"
        }
    },
    "extra": {
        "statamic": {
            "name": "Example",
            "description": "Example addon"
        },
        "laravel": {
            "providers": [
                "Acme\\Example\\ServiceProvider"
            ]
        }
    }
}
```

### Service Provider

Must extend `Statamic\Providers\AddonServiceProvider`, NOT `Illuminate\Support\ServiceProvider`.

Use `bootAddon()` instead of `boot()` — ensures execution after Statamic has booted.

```php
<?php

namespace Acme\Example;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    //
}
```

### Installing during development

Add to project root `composer.json`:

```json
{
    "require": {
        "acme/example": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "addons/example"
        }
    ]
}
```

Run `composer update`. Should see:
```
Discovered Package: acme/example
Discovered Addon: acme/example
```

## Registering Components

Auto-loaded if in correct directories. Manual registration via properties:

```php
protected $tags = [];
protected $modifiers = [];
protected $fieldtypes = [];
protected $widgets = [];
protected $commands = [];
```

## Assets

### Vite (recommended for CSS/JS)

See Vite Tooling docs.

### Publishables

```php
protected $publishables = [
    __DIR__.'/../resources/images' => 'images',
];
```

### Publishing

Files from `$vite`, `$scripts`, `$stylesheets`, `$publishables` are tagged with addon slug.

On `statamic:install`:
```shell
php artisan vendor:publish --tag=your-addon-slug --force
```

Prevent auto-publish:
```php
protected $publishAfterInstall = false;
```

## Routing

### Auto-discovery

```
routes/
    cp.php       # prefixed /cp, with authorization
    actions.php  # prefixed /!/addon-name
    web.php      # no prefix, standard Laravel routes
```

### Manual registration

```php
protected $routes = [
    'cp' => __DIR__.'/../routes/cp.php',
    'actions' => __DIR__.'/../routes/actions.php',
    'web' => __DIR__.'/../routes/web.php',
];
```

### Inline routes in bootAddon

```php
public function bootAddon()
{
    $this->registerCpRoutes(function () {
        Route::get(...);
    });
    $this->registerWebRoutes(function () {
        Route::get(...);
    });
    $this->registerActionRoutes(function () {
        Route::get(...);
    });
}
```

### Route Model Binding

Auto-converted: `collection`, `entry`, `taxonomy`, `term`, `asset_container`, `asset`, `global`, `site`, `revision`, `form`, `user`

## Middleware

```php
protected $middlewareGroups = [
    'statamic.cp.authenticated' => [
        YourCpMiddleware::class,
    ],
    'web' => [
        YourWebMiddleware::class,
    ],
];
```

Groups: `web`, `statamic.web`, `statamic.cp`, `statamic.cp.authenticated`

## Views

Auto-loaded from `resources/views/` with package name as namespace.

```php
return view('my-addon::foo');
```

Custom namespace:
```php
protected $viewNamespace = 'custom';
```

## Inertia

CP uses Inertia.js. Register Vue components in `cp.js`:

```js
Statamic.booting(() => {
    Statamic.$inertia.register('my-addon::Foo', Foo);
});
```

Controller:
```php
return Inertia::render('my-addon::Foo', ['message' => 'Hello']);
```

## Events

### Auto-discovery

Listeners in `src/Listeners/` — event type-hinted in `handle()` or `__invoke()`.
Subscribers in `src/Subscribers/`.

### Manual registration

```php
protected $listen = [
    OrderShipped::class => [
        SendShipmentNotification::class,
    ],
];

protected $subscribe = [
    UserEventSubscriber::class,
];
```

## Scheduling

```php
protected function schedule($schedule)
{
    $schedule->command('something')->daily();
}
```

## Editions

In `composer.json`:
```json
{
    "extra": {
        "statamic": {
            "editions": ["free", "pro"]
        }
    }
}
```

Check:
```php
$addon = Addon::get('vendor/package');
if ($addon->edition() === 'pro') { ... }
```

## Settings

### Define via YAML (auto-discovered)

`resources/blueprints/settings.yaml`

### Define programmatically

```php
public function bootAddon()
{
    $this->registerSettingsBlueprint([
        'tabs' => [
            'main' => [
                'sections' => [
                    [
                        'display' => __('API'),
                        'fields' => [
                            [
                                'handle' => 'api_key',
                                'field' => ['type' => 'text', 'display' => 'API Key', 'validate' => 'required'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
}
```

### Settings UI

CP > Tools > Addons

### Antlers support

Settings can reference config: `{{ config:services:example:api_key }}`

### Storage

`resources/addons/{slug}.yaml` by default. DB via `php please install:eloquent-driver`.

### API

```php
use Statamic\Facades\Addon;

$addon = Addon::get('vendor/package');

// Read
$addon->settings()->get('api_key');
$addon->settings()->all();
$addon->settings()->raw(); // no Antlers eval

// Write
$addon->settings()->set('api_key', 'value');
$addon->settings()->set(['key1' => 'val1', 'key2' => 'val2']);
$addon->settings()->save();
```

## Update Scripts

```php
use Statamic\UpdateScripts\UpdateScript;

class UpdatePermissions extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion)
    {
        return $this->isUpdatingTo('1.2.0');
    }

    public function update()
    {
        // migration logic
        $this->console()->info('Done!');
    }
}
```

## Post-install commands

```php
public function bootAddon()
{
    Statamic::afterInstalled(function ($command) {
        $command->call('some:command');
    });
}
```

## Testing

Auto-scaffolded. See "Testing in Addons" guide.
