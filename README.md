<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/options-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel">
    <img src="art/hero.png" alt="Options for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/options-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/options-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/options-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/options-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/options-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/options-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Options for Laravel

Manage global or per-entity options, settings, and preferences with typed casts, a fluent
API, persistent caching, events, validation, and console tooling.

Each option is a small class describing its key, human-readable name, default value, and how
its value is cast. Options can be global or scoped to any Eloquent model (a user, a team, a
tenant, …). Stored values are memoised for the current request (or queued job) and, optionally,
cached across requests.

## Requirements

- PHP `^8.4`
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/options-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="options-migrations"
php artisan migrate
```

The migration is **not** loaded automatically — the package only publishes it. Until you publish
it, `php artisan migrate` will not create the `options` table.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="options-config"
```

## Configuration

The published `config/options.php` exposes the model, the owner key type, an optional
string-key registry, persistent caching, events, groups, access control and the config bridge:

```php
return [
    'model' => RoundlyConsulting\Options\Option::class,

    'key_type' => env('OPTIONS_KEY_TYPE', 'bigint'),

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

    'groups' => [
        // 'billing' => App\Settings\BillingSettings::class,
    ],

    'authorization' => [
        'enabled'  => env('OPTIONS_AUTHORIZATION', false),
        'use_gate' => env('OPTIONS_AUTHORIZATION_GATE', false),
    ],

    'config_overrides' => [
        // 'mail.from.address' => App\Options\MailFromAddressOption::class,
    ],

    'config_overrides_live' => env('OPTIONS_CONFIG_OVERRIDES_LIVE', false),
];
```

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | `class-string` | `Option::class` | — | Eloquent model used to persist options. Must extend `RoundlyConsulting\Options\Option`. |
| `key_type` | `string` | `bigint` | `OPTIONS_KEY_TYPE` | Key type of the polymorphic `owner` column: `bigint`, `uuid` or `ulid` (case-insensitive; anything else throws `InvalidConfigurationException` when the migration runs). Fixed when the migration runs, so set it before publishing the migration. |
| `registry` | `array<string, class-string>` | `[]` | — | Optional map of string keys to option classes for key-based access. |
| `cache.enabled` | `bool` | `true` | `OPTIONS_CACHE_ENABLED` | Enable the persistent (cross-request) cache layer. |
| `cache.store` | `?string` | `null` | `OPTIONS_CACHE_STORE` | Cache store name; `null` uses the default store. |
| `cache.ttl` | `?int` | `3600` | `OPTIONS_CACHE_TTL` | Cache lifetime in seconds; `null` caches forever. |
| `cache.prefix` | `string` | `options` | `OPTIONS_CACHE_PREFIX` | Cache key prefix. |
| `cache.tag` | `string` | `options` | `OPTIONS_CACHE_TAG` | Cache tag, so a taggable store (Redis/Memcached) also purges old entries on a flush. |
| `events.enabled` | `bool` | `true` | `OPTIONS_EVENTS_ENABLED` | Dispatch `OptionSet`/`OptionForgotten` events. |
| `events.resolved` | `bool` | `false` | `OPTIONS_EVENTS_RESOLVED` | Also dispatch `OptionResolved` on every read (off by default — it is chatty). |
| `groups` | `array<string, class-string>` | `[]` | — | Optional map of short keys to `OptionGroup` classes for `Options::group('key')`. |
| `authorization.enabled` | `bool` | `false` | `OPTIONS_AUTHORIZATION` | Enforce per-option `authorizeRead()`/`authorizeWrite()` hooks. Off by default. |
| `authorization.use_gate` | `bool` | `false` | `OPTIONS_AUTHORIZATION_GATE` | Also consult the Gate abilities `option.read`/`option.write` when defined. |
| `config_overrides` | `array<string, class-string>` | `[]` | — | Map of `config()` keys to options whose stored value overrides config at boot. |
| `config_overrides_live` | `bool` | `false` | `OPTIONS_CONFIG_OVERRIDES_LIVE` | Re-apply a mapped config key in-process on `set()`/`forget()`. Off by default. |

The `bool` switches accept the usual env spellings: `true`/`false`, `1`/`0`, `on`/`off`, `yes`/`no`.

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

Override the hooks to customise it (`key`, `default`, `castAs`, `encrypted`, `rules`,
`authorizeRead/Write`, `label`/`help`/`section`/`order`). The value operations — `value`, `set`,
`has`, `forget`, `reset`, `remember` — are final: they run through the `Options` manager, which is
what keeps an option instance, the facade and the test fake in step.

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

Options::export();                                // list<OptionPayload>: every stored option
Options::export($team);                           // one owner's options
Options::export(globalOnly: true);                // only the global ones
Options::exportJson($team);                       // the same as a JSON string
Options::import($json);                           // JSON, decoded rows or OptionPayloads — upsert
Options::for($team)->export();                    // one scope (for(null) = global only)
```

Every value operation — the facade, `for()` / `option()` / `key()` / `group()`, an option
instance (`ThemeOption::for($user)->set('dark')`) and the `HasOptions` trait — goes through the
same `OptionsManager`, so they behave identically and `Options::fake()` sees them all.

**Without the facade.** Inject the manager — same API — or call the export/import actions:

```php
use RoundlyConsulting\Options\Actions\ExportOptionsAction;
use RoundlyConsulting\Options\Actions\ImportOptionsAction;
use RoundlyConsulting\Options\OptionsManager;

final class CopyTeamSettings
{
    public function __construct(private OptionsManager $options) {}

    public function __invoke(Team $from): string
    {
        return $this->options->exportJson($from);
    }
}

$payloads = app(ExportOptionsAction::class)->execute($team);   // list<OptionPayload>
app(ImportOptionsAction::class)->execute($payloads);           // int
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

`setMany()` is all or nothing: every value is authorized and validated before the first write,
and the writes share one database transaction. `all()` returns the raw stored strings (an
encrypted option stays ciphertext); with [access control](#access-control) on, it leaves out
what the current user may not read.

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

$user->option(SimpleOption::class)->value();   // same as Options::get(SimpleOption::class, $user)
```

### Casts

`castAs()` accepts any Laravel cast string, a custom `CastsAttributes` (class-string or
instance), or one of the package casts:

```php
use RoundlyConsulting\Options\Casts\EnumCast;

public function castAs(): string|CastsAttributes
{
    return EnumCast::class.':'.Status::class; // backed-enum cast (string- or int-backed)
}
```

Reads always return the cast type — including right after `set()`, which stores the value the
way the database would and caches that, not your input. Setting `'active'` on the option above
returns `Status::Active`; an `integer` option set to `'5'` returns `5`; a `collection` option set
to an array returns a `Collection`.

Set `encrypted()` to `true` to encrypt the value at rest (the value is serialized through its
cast, encrypted with Laravel's encrypter, and decrypted and cast back on read, so an encrypted
`integer` still reads as an `int`). The persistent cache only ever holds the ciphertext.
Encrypted options must declare `castAs()` as a string (`'integer'`, `'boolean'`, `'collection'`,
a cast class-string, …) — a cast *instance* throws `EncryptionNotSupported`:

```php
public function encrypted(): bool
{
    return true;
}
```

### Validation

Return Laravel validation rules from `rules()` to validate on write (empty by default, so a write
is only validated when the option returns rules):

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
`?Model $owner`, and (for set/resolved) the cast value — the same value `get()` returns.

```php
use RoundlyConsulting\Options\Events\OptionSet;

Event::listen(OptionSet::class, function (OptionSet $event): void {
    // $event->key, $event->value, $event->owner
});
```

### Setting groups

Bundle related options into a declarative group for settings/admin screens. A group lists its
member option classes; `definition()` returns everything a UI needs to render a form.

```php
use RoundlyConsulting\Options\Groups\OptionGroup;

final class AppearanceSettings extends OptionGroup
{
    public function label(): string { return 'Appearance'; }

    public function description(): ?string { return 'Look and feel.'; }

    /** @return list<class-string<\RoundlyConsulting\Options\OptionInterface>> */
    public function options(): array
    {
        return [LocaleOption::class, ThemeOption::class];
    }
}
```

```php
// Resolve current values, keyed by option key:
Options::group(AppearanceSettings::class)->for($user)->all();
// ['locale' => 'en', 'theme' => 'dark']

// Introspect for a UI — group label/description plus an ordered list of definitions
// (label, help, section, order, type, encrypted, current, default):
$page = Options::group(AppearanceSettings::class)->for($user)->definition();

// Bulk write by option key or class-string — all or nothing, like setMany(): every value is
// authorized and validated before the first write, and the writes share a transaction
// (then cast, events + observers fire):
Options::group(AppearanceSettings::class)->for($user)->set([
    'theme'           => 'dark',
    LocaleOption::class => 'sk',
]);
```

Options can override presentation metadata used by `definition()` (all default sensibly):

```php
public function label(): string   { return 'UI Theme'; }
public function help(): ?string   { return 'Light or dark.'; }
public function section(): ?string { return 'general'; }
public function order(): int      { return 10; }
```

Scaffold a group with `php artisan make:option-group AppearanceSettings --options="App\Options\ThemeOption,App\Options\LocaleOption"`.
Reference a group by short key by mapping it under `options.groups`.

> `definition()` reads each option's current value, so it triggers one read per member (and a
> `OptionResolved` event each if that event is enabled). This is fine for an admin screen.

### Access control

Per-option authorization is **opt-in** and **off by default** (existing options are never
gated). Enable it with `options.authorization.enabled`, then declare rules on an option:

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class MaintenanceModeOption extends BaseOption
{
    public function authorizeWrite(?Authenticatable $user, ?Model $owner): bool
    {
        return (bool) $user?->is_admin;
    }
}
```

```php
Options::set(MaintenanceModeOption::class, true);   // throws UnauthorizedOption for non-admins

// Act on behalf of a user, or bypass entirely (e.g. system jobs):
Options::actingAs($user, fn () => Options::set(MaintenanceModeOption::class, true));
Options::withoutAuthorization(fn () => Options::set(MaintenanceModeOption::class, true));
```

Both `authorizeRead()` and `authorizeWrite()` default to `true`. Reads (`get`, `has`, `many`,
the fluent API, helpers, and the `@option` directive) and writes (`set`, `setMany`, group
`set`, `remember`, `forget`, `reset`) are enforced through one seam, so all access paths respect
the rules. `all()` does not throw: it leaves out every option the current user may not read —
and, since it can only check an option it knows, every stored key that is not in the
[string-key registry](#string-key-registry). With `authorization.use_gate` on, the Gate
abilities `option.read`/`option.write` are also consulted **when defined** (they receive the
option instance and owner).

System code bypasses authorization: the [config bridge](#config-bridge) reads its options
unguarded (it runs at boot, before any user exists), and so do the console commands —
`options:list`, `options:get` and `options:set`. Pass `--as=<userKey>` to `options:get` /
`options:set` to run under a specific user's rules instead.

### Config bridge

Let DB-backed options transparently override Laravel `config()` values. Map config keys to
options under `options.config_overrides`; at boot each mapped option's stored value is pushed
into config (global scope only). A key whose option has no stored value keeps its existing
config value, so framework/`.env` defaults are never clobbered.

```php
// config/options.php
'config_overrides' => ['mail.from.address' => MailFromAddressOption::class],
```

```php
Options::set(MailFromAddressOption::class, 'hello@acme.test');
// config('mail.from.address') === 'hello@acme.test' after the next boot

// Register a mapping at runtime (applied immediately):
Options::overrides('app.name', AppNameOption::class);
Options::configOverrides();      // the active map
Options::applyConfigOverrides(); // re-read every mapped option into config() now
```

With `options.config_overrides_live` enabled, `set()`/`forget()` re-apply (or revert) the
mapped config key in the current process. The bridge reads its options without
[access control](#access-control), so an option guarded by `authorizeRead()` still reaches config.

> **Boot-order caveat:** only code that reads a mapped config key *after* the package boots
> sees the override. Config consumed earlier in the bootstrap — or read from a `config:cache`d
> file before providers boot — is unaffected.

### Observers

React to a *specific* option changing without wiring a global event listener. Observers fire
on `set()` and `forget()` (riding the existing events, so they require `options.events.enabled`),
in registration order, and receive `(mixed $value, ?Model $owner, OptionChange $change)`.

```php
use RoundlyConsulting\Options\DataTransferObjects\OptionChange;

Options::observe(ThemeOption::class, function (mixed $value, ?Model $owner, OptionChange $change): void {
    // $change->type is OptionChangeType::Set or ::Forgotten
    Cache::forget('compiled-theme');
});

// Invokable class-string (resolved from the container):
Options::observe(ThemeOption::class, RecompileThemeListener::class);

Options::forgetObservers(ThemeOption::class); // drop one option's observers
Options::flushObservers();                    // drop them all
```

Observers do not fire on reads. The in-memory test fake fires them directly, so
`Options::fake()` + `observe()` works in host tests.

### Caching

Two layers sit in front of the database:

- **An in-request memo.** It lives for one request or one queued job — it is bound `scoped` in
  the container and also dropped when a queue job starts or Octane receives a request — so a
  long-running worker never keeps serving a value another process has since changed.
- **A persistent cache** (when `options.cache.enabled` is true) in any Illuminate cache store.
  Writes update it and deletes drop the entry, so every process sees a change from its next
  request or job on.

Both hold the raw stored string, never the cast value: an encrypted option stays ciphertext in
the cache, and nothing but scalars is serialized, so Laravel's unserialize hardening
(`cache.serializable_classes`) cannot turn a cached `Collection` or `Carbon` into
`__PHP_Incomplete_Class`. Each read casts the raw value.

```php
Options::flushCache(); // clears the in-request memo and the persistent entries
```

`flushCache()` and `php artisan options:clear-cache` work on every store: each cache key embeds a
generation token, and a flush moves to a new one, so no old entry is read again — also by other
processes, from their next request on. A taggable store (Redis/Memcached) also deletes the old
entries at once; on any other store (file, database) they expire by `cache.ttl`, and with a
`null` TTL they stay until the store evicts them.

### Import / export

```php
$json = Options::exportJson();          // all options (raw stored values; encrypted stay encrypted)
Options::import($json);                 // upsert by scope + key, returns the count
```

Owner ids follow `options.key_type`: an int for bigint owners, a string for uuid/ulid owners. A
row with only one of `owner_type` / `owner_id` is imported as global. Imports drop the cached
value of every imported key, so the next read sees the imported value.

The table holds at most one row per option and scope (a unique index over the scope and key),
so two requests writing a new option at the same moment cannot store it twice: the second
write updates the first one's row. A forgotten option's (soft-deleted) row is reused by the
next `set()` or import.

### Blade directive

```blade
{{-- registered key or class-string; output is HTML-escaped --}}
@option('theme')
@option(\App\Options\ThemeOption::class)
```

### Console commands

```bash
php artisan make:option ThemeOption --cast=string --key=theme --encrypted --enum="App\Enums\Status"
php artisan make:option-group AppearanceSettings --options="App\Options\ThemeOption,App\Options\LocaleOption"
php artisan options:list   [--owner=App\Models\User --owner-id=5]
php artisan options:get    {option} [--owner= --owner-id= --as=<userKey>]
php artisan options:set    {option} {value} [--owner= --owner-id= --json --as=<userKey>]
php artisan options:clear-cache
php artisan options:export [--owner= --owner-id= --path=storage/options.json]
php artisan options:import {path}
```

`{option}` is a registered key or an option class-string. `make:option-group` writes each
`--options` class fully qualified (`\App\Options\ThemeOption::class`), so it resolves from the
group's own namespace. The commands bypass [access control](#access-control).

### Testing

`Options::fake()` swaps the manager for an in-memory store: nothing touches the database, and every
write is recorded — made through the facade, an injected manager, a handle, an option instance or
the `HasOptions` trait. It behaves like the real manager where a test would notice: writes are
validated against `rules()` (an invalid one throws and is not recorded), reads return the cast
type, `setMany()` / group writes validate the whole batch first, and keys you registered with
`Options::register()` before calling `fake()` still resolve. Authorization and observers still
run; events and caches do not.

```php
use RoundlyConsulting\Options\Facades\Options;

$fake = Options::fake();

$user->option(ThemeOption::class)->set('dark');
Options::import($json);

$fake->assertSet(ThemeOption::class, 'dark', $user);
$fake->assertImported(fn (array $payloads) => count($payloads) === 3);
$fake->assertNothingForgotten();
```

| Assertion | Opposite |
|---|---|
| `assertSet($option, $value = null, ?$owner = null)` | `assertNothingSet()` |
| `assertForgotten($option, ?$owner = null)` | `assertNothingForgotten()` |
| `assertImported(?fn (list<OptionPayload>): bool)` | `assertNothingImported()` |

Every assertion also works statically (`Options::assertSet(...)`). `assertSet()` compares the
value as you passed it. `export()` / `exportJson()` under the fake read the in-memory store,
which holds what the database would — raw strings, ciphertext for an encrypted option.

The `Option` factory also ships states: `global()`, `forOwner($model)`, `value($v)`,
`withMeta([...])`.

Run the package test suite with:

```bash
composer test
```

## Integrates with

This package builds on other roundly-consulting packages:

- **[package-toolkit-for-laravel](https://github.com/roundly-consulting/package-toolkit-for-laravel)**
  — a hard dependency. It provides the service-provider builder (config, publish-only migrations,
  commands, the `@option` Blade directive and the publish tags) and the validated `options.model` resolver,
  which checks that a swapped-in model really is an option model before the package queries
  through it. The package also reports its configuration to Laravel's `about` command
  (`php artisan about --only=options`); option keys, group keys and bridged config paths are
  reported by **count only** — a key names a host's setting and its value is arbitrary host data
  (tokens, feature flags, PII), so neither ever renders.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=options-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE](LICENSE.md) for details.
