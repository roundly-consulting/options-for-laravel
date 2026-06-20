<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class FlagOption extends BaseOption
{
    public function key(): string
    {
        return 'flag';
    }

    public function default(): mixed
    {
        return false;
    }

    public function castAs(): string|CastsAttributes
    {
        return 'boolean';
    }
}
