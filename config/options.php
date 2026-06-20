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
];
