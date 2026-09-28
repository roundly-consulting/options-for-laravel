<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Casts\EnumCast;

class PriorityOption extends BaseOption
{
    public function key(): string
    {
        return 'priority';
    }

    public function castAs(): string|CastsAttributes
    {
        return EnumCast::class.':'.Priority::class;
    }
}
