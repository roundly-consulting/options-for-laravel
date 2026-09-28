<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Options\CollectionOption;
use RoundlyConsulting\Options\Tests\Options\EncryptedIntegerOption;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\Status;
use RoundlyConsulting\Options\Tests\Options\StatusOption;

/**
 * A file cache store in a throwaway directory, so a test can read what really
 * landed in the persistent cache and apply Laravel's unserialize hardening.
 *
 * @param  array<int, class-string>|bool|null  $serializableClasses
 */
function useFileOptionCache(array|bool|null $serializableClasses = null): string
{
    $directory = sys_get_temp_dir().'/options-cache-'.Str::random(12);

    config()->set('cache.stores.options_file', ['driver' => 'file', 'path' => $directory]);
    config()->set('cache.serializable_classes', $serializableClasses);
    config()->set('options.cache.store', 'options_file');

    return $directory;
}

function nextRequest(): void
{
    app(Cache::class)->flush();
}

beforeEach(function (): void {
    nextRequest();
    $this->cacheDirectory = null;
});

afterEach(function (): void {
    if (is_string($this->cacheDirectory)) {
        File::deleteDirectory($this->cacheDirectory);
    }
});

it('returns the cast value right after set, not the raw input', function (): void {
    Options::set(StatusOption::class, 'active');
    Options::set(CollectionOption::class, ['email' => false]);
    Options::set(EncryptedIntegerOption::class, '5');

    expect(Options::get(StatusOption::class))->toBe(Status::Active)
        ->and(Options::get(CollectionOption::class))->toBeInstanceOf(Collection::class)
        ->and(Options::get(CollectionOption::class)->get('email'))->toBeFalse()
        ->and(Options::get(EncryptedIntegerOption::class))->toBe(5);
});

it('remembers the cast value on the first call too', function (): void {
    expect(Options::remember(StatusOption::class, fn (): string => 'active'))->toBe(Status::Active)
        ->and(Options::remember(StatusOption::class, fn (): string => 'inactive'))->toBe(Status::Active);
});

it('serves the cast value from the persistent cache on the next request', function (): void {
    Options::set(StatusOption::class, 'active');
    Options::set(CollectionOption::class, ['email' => false]);

    nextRequest();

    expect(Options::get(StatusOption::class))->toBe(Status::Active)
        ->and(Options::get(CollectionOption::class)?->all())->toBe(['email' => false]);
});

it('never puts an encrypted option in plaintext into the persistent cache', function (): void {
    $this->cacheDirectory = useFileOptionCache();

    SecretOption::make()->set('sk_live_TOPSECRET');
    nextRequest();

    expect(SecretOption::make()->value())->toBe('sk_live_TOPSECRET');

    $cached = collect(File::allFiles($this->cacheDirectory))
        ->map(fn (SplFileInfo $file): string => (string) file_get_contents($file->getPathname()))
        ->implode("\n");

    expect($cached)->not->toBe('')
        ->and($cached)->not->toContain('TOPSECRET');
});

it('caches no objects, so hardened unserialization still rebuilds collections', function (): void {
    $this->cacheDirectory = useFileOptionCache(serializableClasses: false);

    expect(Options::get(CollectionOption::class))->toBeInstanceOf(Collection::class);

    nextRequest();

    expect(Options::get(CollectionOption::class))->toBeInstanceOf(Collection::class)
        ->and(Options::get(CollectionOption::class)?->all())->toBe(['default' => 'yes']);

    Options::set(CollectionOption::class, collect(['email' => true]));
    nextRequest();

    expect(Options::get(CollectionOption::class)?->all())->toBe(['email' => true]);
});
