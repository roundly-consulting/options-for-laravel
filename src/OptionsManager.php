<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Support\Cache;

final class OptionsManager
{
    /**
     * Resolve an option instance, optionally bound to an owner model.
     *
     * @param  class-string<OptionInterface>  $option
     */
    public function resolve(string $option, ?Model $owner = null): OptionInterface
    {
        if (! class_exists($option) || ! is_subclass_of($option, OptionInterface::class)) {
            throw InvalidOptionClassName::for($option);
        }

        return $option::for($owner);
    }

    /**
     * Read the current value of an option.
     *
     * @param  class-string<OptionInterface>  $option
     */
    public function get(string $option, ?Model $owner = null): mixed
    {
        return $this->resolve($option, $owner)->value();
    }

    /**
     * Persist a new value for an option.
     *
     * @param  class-string<OptionInterface>  $option
     */
    public function set(string $option, mixed $value, ?Model $owner = null): void
    {
        $this->resolve($option, $owner)->set($value);
    }

    /**
     * Flush the in-request option value cache.
     */
    public function flushCache(): void
    {
        Cache::getInstance()->flush();
    }
}
