<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Traits;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\OptionsManager;

/**
 * @mixin Model
 */
trait HasOptions
{
    /**
     * Resolve an option bound to this model as its owner.
     *
     * @param  class-string<OptionInterface>  $option
     */
    public function option(string $option): OptionInterface
    {
        return app(OptionsManager::class)->resolve($option, $this);
    }
}
