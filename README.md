# Options for Laravel

Manage global or per-entity options, settings, and preferences with typed casts, a fluent
API, persistent caching, events, validation, and console tooling.

Each option is a small class describing its key, human-readable name, default value, and how
its value is cast. Options can be global or scoped to any Eloquent model (a user, a team, a
tenant, …). Resolved values are memoised for the current request and, optionally, cached
across requests.

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

The published `config/options.php` exposes the model, an optional string-key registry,
persistent caching, and events:

```php
return [
    'model' => RoundlyConsulting\Options\Option::class,

    'registry' => [
        // 'theme' => App\Options\ThemeOption::class,
    ],

    'cache' => [
        'enabled' => env('OPTIONS_CACHE_ENABLED', true),
        'store'   => env('OPTIONS_CACHE_STORE'),
        'ttl'     => env('OPTIONS_CACHE_TTL', 3600),
        'prefix'  => env('OPTIONS_CACHE_PREFIX', 'options'),
        'tag'     => env('OPTIONS_CACHE_TAG', 'options'),
    ],

    'events' => [
        'enabled'  => env('OPTIONS_EVENTS_ENABLED', true),
        'resolved' => env('OPTIONS_EVENTS_RESOLVED', false),
    ],
];
```

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | `class-string` | `Option::class` | — | Eloquent model used to persist options. Must extend `RoundlyConsulting\Options\Option`. |
| `registry` | `array<string, class-string>` | `[]` | — | Optional map of string keys to option classes for key-based access. |
| `cache.enabled` | `bool` | `true` | `OPTIONS_CACHE_ENABLED` | Enable the persistent (cross-request) cache layer. |
| `cache.store` | `?string` | `null` | `OPTIONS_CACHE_STORE` | Cache store name; `null` uses the default store. |
| `cache.ttl` | `?int` | `3600` | `OPTIONS_CACHE_TTL` | Cache lifetime in seconds; `null` caches forever. |
| `cache.prefix` | `string` | `options` | `OPTIONS_CACHE_PREFIX` | Cache key prefix. |
| `cache.tag` | `string` | `options` | `OPTIONS_CACHE_TAG` | Cache tag used for bulk invalidation (taggable stores only). |
| `events.enabled` | `bool` | `true` | `OPTIONS_EVENTS_ENABLED` | Dispatch `OptionSet`/`OptionForgotten` events. |
| `events.resolved` | `bool` | `false` | `OPTIONS_EVENTS_RESOLVED` | Also dispatch `OptionResolved` on every read (off by default — it is chatty). |

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

Override any method to customise behaviour:

```php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

final class NotificationPreferences extends BaseOption
{
    public function key(): string
    {
        return 'notification-preferences';
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

Generate one with the `make:option` command:

```bash
php artisan make:option ThemeOption --cast=string --key=theme
php artisan make:option SecretToken --encrypted
php artisan make:option StatusOption --enum="App\Enums\Status"
```

### The `Options` facade

```php
use RoundlyConsulting\Options\Facades\Options;

Options::resolve(SimpleOption::class);            // OptionInterface
Options::get(SimpleOption::class);                // current value
Options::set(SimpleOption::class, 'value');       // persist a value
Options::get(SimpleOption::class, $user);         // scoped read
Options::set(SimpleOption::class, 'value', $user); // scoped write
Options::has(SimpleOption::class);                // is a value stored?
Options::forget(SimpleOption::class);             // delete (revert to default)
Options::reset(SimpleOption::class);              // alias of forget()
Options::remember(SimpleOption::class, fn () => compute());
```

### Fluent API

```php
Options::option(ThemeOption::class)->set('dark');
Options::option(ThemeOption::class)->get();
Options::option(ThemeOption::class)->has();
Options::option(ThemeOption::class)->forget();
Options::option(ThemeOption::class)->default();
Options::option(ThemeOption::class)->remember(fn () => 'dark');

// Owner-scoped
Options::for($user)->option(ThemeOption::class)->set('light');
Options::for($user)->get(ThemeOption::class);
Options::for($user)->set(ThemeOption::class, 'light');
```

### Bulk operations

```php
$values = Options::many([ThemeOption::class, LocaleOption::class]);
Options::setMany([ThemeOption::class => 'dark', LocaleOption::class => 'en']);

// Everything stored for a scope (raw key => value), in one query
$all = Options::all();          // global
$all = Options::for($user)->all();
```

### Helpers

`options()` and its alias `setting()` cover the common cases (both are `function_exists`
guarded so a host app may override them):

```php
options();                              // OptionsManager instance
options(ThemeOption::class);            // global value
options(ThemeOption::class, $user);     // scoped value
options()->set(ThemeOption::class, 'dark');

setting('theme');                       // identical alias
```

### String-key registry

Register short keys so options can be resolved by string (great for Blade and config-driven
UIs). Register in a service provider or via `config('options.registry')`:

```php
Options::register([
    'theme'  => ThemeOption::class,
    'locale' => LocaleOption::class,
]);

Options::key('theme')->get();
options('theme', $user);
Options::for($user)->key('theme')->set('dark');
```

Both the registry and class-string forms funnel through one resolver; an unknown key or class
throws `RoundlyConsulting\Options\Exceptions\InvalidOptionClassName`.

### The `HasOptions` trait

```php
use RoundlyConsulting\Options\Traits\HasOptions;

final class User extends Authenticatable
{
    use HasOptions;
}

$user->option(SimpleOption::class)->value();
```

### Casts

`castAs()` accepts any Laravel cast string, a custom `CastsAttributes`, or one of the package
casts:

```php
use RoundlyConsulting\Options\Casts\EnumCast;

public function castAs(): string|CastsAttributes
{
    return EnumCast::class.':'.Status::class; // backed-enum cast
}
```

Set `encrypted()` to `true` to encrypt the value at rest (the serialized value is encrypted
with Laravel's `encrypt()` and transparently decrypted on read). Encrypted options must
declare `castAs()` as a string (`'string'`, `'collection'`, or a cast class-string):

```php
public function encrypted(): bool
{
    return true;
}
```

### Validation

Return Laravel validation rules from `rules()` to validate on write (empty by default, so no
behaviour change unless you opt in):

```php
public function rules(): array|string
{
    return ['integer', 'min:0'];
}
```

Invalid values throw `Illuminate\Validation\ValidationException`.

### Events

`OptionSet` and `OptionForgotten` fire on writes/deletes; `OptionResolved` fires on reads
when `options.events.resolved` is enabled. Each event carries the option `key`, the
`?Model $owner`, and (for set/resolved) the value.

```php
use RoundlyConsulting\Options\Events\OptionSet;

Event::listen(OptionSet::class, function (OptionSet $event): void {
    // $event->key, $event->value, $event->owner
});
```

### Caching

Resolved values are memoised in-request and, when `options.cache.enabled` is true, stored in
a persistent Illuminate cache. Writes and deletes invalidate both caches automatically. With
a taggable store (Redis/Memcached) `flushCache()` purges everything at once; with a
non-taggable store entries expire by TTL.

```php
Options::flushCache(); // clears the in-request memo and persistent entries
```

### Import / export

```php
use RoundlyConsulting\Options\Actions\ExportOptionsAction;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;

$json = app(ExportOptionsAction::class)->toJson();   // all options
app(ImportOptionsAction::class)->fromJson($json);    // upsert by scope + key
```

### Blade directive

```blade
{{-- registered key or class-string; output is HTML-escaped --}}
@option('theme')
@option(\App\Options\ThemeOption::class)
```

### Console commands

```bash
php artisan make:option ThemeOption --cast=string --key=theme --encrypted --enum="App\Enums\Status"
php artisan options:list   [--owner=App\Models\User --owner-id=5]
php artisan options:get    {option} [--owner= --owner-id=]
php artisan options:set    {option} {value} [--owner= --owner-id= --json]
php artisan options:clear-cache
php artisan options:export [--owner= --owner-id= --path=storage/options.json]
php artisan options:import {path}
```

`{option}` is a registered key or an option class-string.

### Testing

Swap the manager for an in-memory fake so your tests never touch the database:

```php
use RoundlyConsulting\Options\Facades\Options;

$fake = Options::fake();

Options::set(ThemeOption::class, 'dark');

$fake->assertSet(ThemeOption::class, 'dark');
$fake->assertForgotten(ThemeOption::class);
$fake->assertNothingSet();
```

The `Option` factory also ships states: `global()`, `forOwner($model)`, `value($v)`,
`withMeta([...])`.

Run the package test suite with:

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
