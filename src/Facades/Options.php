<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Facades;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Options\OptionContext;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\PendingOptions;
use RoundlyConsulting\Options\Testing\FakeOptionsManager;

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
 * @method static PendingOptions for(?Model $owner)
 * @method static OptionContext option(string $option)
 * @method static OptionContext key(string $key)
 * @method static void register(array<string, class-string<OptionInterface>> $options)
 * @method static array<string, class-string<OptionInterface>> registered()
 * @method static class-string<OptionInterface> resolveClass(string $option)
 * @method static void flushCache()
 *
 * @see OptionsManager
 */
final class Options extends Facade
{
    public static function fake(): FakeOptionsManager
    {
        return OptionsManager::fake();
    }

    protected static function getFacadeAccessor(): string
    {
        return OptionsManager::class;
    }
}
