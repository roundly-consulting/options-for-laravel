<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class DateOption extends BaseOption
{
    public function key(): string
    {
        return 'date';
    }

    public function castAs(): string|CastsAttributes
    {
        return 'immutable_datetime';
    }
}
