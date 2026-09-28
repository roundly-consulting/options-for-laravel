<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Facades;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\Groups\PendingGroup;
use RoundlyConsulting\Options\OptionContext;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\PendingOptions;
use RoundlyConsulting\Options\Testing\OptionsFake;

/**
 * @method static OptionInterface resolve(string $option, ?Model $owner = null)
 * @method static mixed get(string $option, ?Model $owner = null)
 * @method static void set(string $option, mixed $value, ?Model $owner = null)
 * @method static bool has(string $option, ?Model $owner = null)
 * @method static void forget(string $option, ?Model $owner = null)
 * @method static void reset(string $option, ?Model $owner = null)
 * @method static mixed remember(string $option, Closure $callback, ?Model $owner = null)
 * @method static array<string, mixed> many(array<int, string> $options, ?Model $owner = null)
 * @method static void setMany(array<string, mixed> $values, ?Model $owner = null)
 * @method static Collection<string, mixed> all(?Model $owner = null)
 * @method static list<OptionPayload> export(?Model $owner = null, bool $globalOnly = false)
 * @method static string exportJson(?Model $owner = null, bool $globalOnly = false)
 * @method static int import(array<mixed>|string $payload)
 * @method static PendingOptions for(?Model $owner)
 * @method static OptionContext option(string $option)
 * @method static OptionContext key(string $key)
 * @method static void register(array<string, class-string<OptionInterface>> $options)
 * @method static array<string, class-string<OptionInterface>> registered()
 * @method static class-string<OptionInterface> resolveClass(string $option)
 * @method static void flushCache()
 * @method static PendingGroup group(string|OptionGroup $group, ?Model $owner = null)
 * @method static mixed actingAs(?Authenticatable $user, Closure $callback)
 * @method static mixed withoutAuthorization(Closure $callback)
 * @method static void overrides(string $configKey, string $option)
 * @method static array<string, class-string<OptionInterface>> configOverrides()
 * @method static void applyConfigOverrides()
 * @method static void observe(string $option, Closure|class-string $callback)
 * @method static void forgetObservers(string $option)
 * @method static void flushObservers()
 * @method static void assertSet(string $option, mixed $value = null, ?Model $owner = null)
 * @method static void assertNothingSet()
 * @method static void assertForgotten(string $option, ?Model $owner = null)
 * @method static void assertNothingForgotten()
 * @method static void assertImported(?Closure $callback = null)
 * @method static void assertNothingImported()
 *
 * The `assert*()` lines exist only after `Options::fake()`.
 *
 * @see OptionsManager
 * @see OptionsFake
 */
final class Options extends Facade
{
    /**
     * Swap in an in-memory store that records every write and never touches the database.
     */
    public static function fake(): OptionsFake
    {
        $fake = app(OptionsFake::class);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return OptionsManager::class;
    }
}
