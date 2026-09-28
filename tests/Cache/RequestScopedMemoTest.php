<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

/**
 * A long-lived process (queue worker, Octane) keeps one PHP process across jobs and
 * requests. The in-request memo must not outlive one of them: another process's write,
 * seen only in the database here (persistent cache off), has to show up in the next one.
 */
beforeEach(function (): void {
    config()->set('options.cache.enabled', false);

    Options::set(ThemeOption::class, 'dark');
    expect(Options::get(ThemeOption::class))->toBe('dark');

    // Another process writes the option.
    DB::table('options')->where('key', 'theme')->update(['value' => 'light-from-admin']);
});

it('memoises within one request', function (): void {
    expect(Options::get(ThemeOption::class))->toBe('dark');
});

it('starts empty when the container resets its scope between jobs', function (): void {
    app()->forgetScopedInstances();

    expect(Options::get(ThemeOption::class))->toBe('light-from-admin');
});

it('starts empty when a queued job starts processing', function (): void {
    Event::dispatch(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

    expect(Options::get(ThemeOption::class))->toBe('light-from-admin');
});

it('starts empty when Octane receives a request', function (): void {
    Event::dispatch('Laravel\Octane\Events\RequestReceived');

    expect(Options::get(ThemeOption::class))->toBe('light-from-admin');
});

it('is bound in the container, not held in a static', function (): void {
    $memo = app(Cache::class);

    expect(app(Cache::class))->toBe($memo);

    app()->forgetScopedInstances();

    expect(app(Cache::class))->not->toBe($memo);
});
