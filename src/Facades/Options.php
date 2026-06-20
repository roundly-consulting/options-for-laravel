<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;

/**
 * @method static OptionInterface resolve(class-string<OptionInterface> $option, ?Model $owner = null)
 * @method static mixed get(class-string<OptionInterface> $option, ?Model $owner = null)
 * @method static void set(class-string<OptionInterface> $option, mixed $value, ?Model $owner = null)
 * @method static void flushCache()
 *
 * @see OptionsManager
 */
final class Options extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OptionsManager::class;
    }
}
