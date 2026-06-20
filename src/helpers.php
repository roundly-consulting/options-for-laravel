<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;

if (! function_exists('options')) {
    /**
     * Resolve the options manager, or read an option value when a key/class is given.
     *
     * @param  class-string<OptionInterface>|string|null  $option
     */
    function options(?string $option = null, ?Model $owner = null): mixed
    {
        $manager = app(OptionsManager::class);

        if ($option === null) {
            return $manager;
        }

        return $manager->get($option, $owner);
    }
}

if (! function_exists('setting')) {
    /**
     * Alias of options() for teams that prefer the "setting" vocabulary.
     *
     * @param  class-string<OptionInterface>|string|null  $option
     */
    function setting(?string $option = null, ?Model $owner = null): mixed
    {
        return options($option, $owner);
    }
}
