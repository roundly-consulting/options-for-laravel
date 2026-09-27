<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Option;

return [
    /*
    |--------------------------------------------------------------------------
    | Option model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist option values. Swap it for your own
    | model (extending the package model) if you need extra behaviour.
    |
    */
    'model' => Option::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic owner column. Use "uuid" or "ulid"
    | when the owner models use UUID/ULID primary keys, otherwise leave it as
    | "bigint". Any unrecognized value falls back to "bigint". It is fixed when
    | the migration first runs, so choose it before publishing the migrations.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */
    'key_type' => env('OPTIONS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | String-key registry
    |--------------------------------------------------------------------------
    |
    | Optional map of short string keys to option class-strings. Once an option
    | is registered here you can read/write it by key — `options('theme')` — in
    | addition to the class-string API. Dotted keys (`profile.theme`) are just a
    | naming convention; they are not stored as groups.
    |
    | @var array<string, class-string<\RoundlyConsulting\Options\OptionInterface>>
    */
    'registry' => [
        // 'theme' => App\Options\ThemeOption::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistent cache
    |--------------------------------------------------------------------------
    |
    | On top of the in-request memo cache, resolved values can be cached in a
    | persistent Illuminate cache store so they survive across requests. Writes
    | and deletes invalidate the cached entry automatically.
    |
    */
    'cache' => [
        'enabled' => env('OPTIONS_CACHE_ENABLED', true),
        'store' => env('OPTIONS_CACHE_STORE'),
        'ttl' => env('OPTIONS_CACHE_TTL', 3600),
        'prefix' => env('OPTIONS_CACHE_PREFIX', 'options'),
        'tag' => env('OPTIONS_CACHE_TAG', 'options'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | OptionSet and OptionForgotten are dispatched on write/delete. OptionResolved
    | fires on every read, so it is opt-in (it can be chatty).
    |
    */
    'events' => [
        'enabled' => env('OPTIONS_EVENTS_ENABLED', true),
        'resolved' => env('OPTIONS_EVENTS_RESOLVED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Setting groups
    |--------------------------------------------------------------------------
    |
    | Optional map of short keys to OptionGroup class-strings so a group can be
    | referenced by key from the CLI or a UI — `Options::group('billing')`.
    |
    | @var array<string, class-string<\RoundlyConsulting\Options\Groups\OptionGroup>>
    */
    'groups' => [
        // 'billing' => App\Settings\BillingSettings::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access control
    |--------------------------------------------------------------------------
    |
    | Opt-in per-option authorization. When enabled, the manager enforces each
    | option's authorizeRead()/authorizeWrite() hooks and, if use_gate is on,
    | also consults the Laravel Gate abilities `option.read`/`option.write`
    | (only when those abilities are defined). Disabled by default.
    |
    */
    'authorization' => [
        'enabled' => env('OPTIONS_AUTHORIZATION', false),
        'use_gate' => env('OPTIONS_AUTHORIZATION_GATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Config bridge
    |--------------------------------------------------------------------------
    |
    | Map framework/app config keys to options so DB-backed values transparently
    | override config() at boot. Only global options are applied; a key whose
    | option has no stored value keeps its existing config value. With
    | config_overrides_live enabled, set()/forget() re-apply the key in-process.
    |
    | @var array<string, class-string<\RoundlyConsulting\Options\OptionInterface>>
    */
    'config_overrides' => [
        // 'mail.from.address' => App\Options\MailFromAddressOption::class,
    ],

    'config_overrides_live' => env('OPTIONS_CONFIG_OVERRIDES_LIVE', false),
];
