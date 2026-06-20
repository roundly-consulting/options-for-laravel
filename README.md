# Options for Laravel

Manage global or per-entity options and preferences with typed casts and in-request caching.

Each option is a small class describing its key, human-readable name, default value, and how
its value is cast. Options can be global or scoped to any Eloquent model (a user, a team, a
tenant, …). Resolved values are memoised for the duration of the request.

## Requirements

- PHP `^8.4`
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/options-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="options-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="options-config"
```

## Configuration

The published `config/options.php`:

```php
<?php

declare(strict_types=1);

return [
    // The Eloquent model used to store option values. Swap it for your own model if you
    // need custom behaviour — it must extend RoundlyConsulting\Options\Option.
    'model' => \RoundlyConsulting\Options\Option::class,
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Options\Option::class` | Eloquent model used to persist options. |

## Usage

### Defining an option

Extend `BaseOption`. With no overrides the key is the class name, the value casts to
`string`, and the default is `null`:

```php
use RoundlyConsulting\Options\BaseOption;

final class SimpleOption extends BaseOption
{
    // Uses all defaults.
}
```

Override any of the methods to customise behaviour:

```php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

final class NotificationPreferences extends BaseOption
{
    public function key(): string
    {
        return 'notification-preferences';
    }

    public function readable(): string
    {
        return 'Notification preferences';
    }

    public function default(): mixed
    {
        return collect(['email' => true, 'sms' => false]);
    }

    public function castAs(): string|CastsAttributes
    {
        return 'collection';
    }
}
```

### Global options

```php
$option = SimpleOption::make();

$option->key();      // "SimpleOption" — stored in the database
$option->readable(); // "Simple Option" — handy for UI
$option->default();  // default value when unset (null by default)
$option->castAs();   // cast applied to the value ("string" by default)
$option->value();    // current value, or the default when unset
$option->set('dark'); // persist a new value
```

### Per-entity options

Scope an option to any Eloquent model, either via `for()` or the `HasOptions` trait:

```php
use RoundlyConsulting\Options\Traits\HasOptions;

final class User extends Authenticatable
{
    use HasOptions;
}
```

```php
$user = auth()->user();

// Equivalent ways to resolve the same scoped option:
$option = SimpleOption::for($user);
$option = $user->option(SimpleOption::class);

$option->set('per-user-value');
$option->value();
```

### The `Options` facade

A facade fronts an `OptionsManager` for resolving and reading/writing options by class name:

```php
use RoundlyConsulting\Options\Facades\Options;

$option = Options::resolve(SimpleOption::class);        // OptionInterface
$value  = Options::get(SimpleOption::class);            // current value
Options::set(SimpleOption::class, 'value');             // persist a value
Options::get(SimpleOption::class, $user);               // scoped to an owner
Options::set(SimpleOption::class, 'value', $user);      // scoped write
```

Passing a class that does not exist or does not implement `OptionInterface` throws
`RoundlyConsulting\Options\Exceptions\InvalidOptionClassName`.

### Caching

Resolved values are cached in memory for the current request and refreshed on write. Each
option/owner pair has its own fingerprint (see `BaseOption::fingerprint()`). Flush the cache
manually when needed:

```php
use RoundlyConsulting\Options\Facades\Options;

Options::flushCache();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
