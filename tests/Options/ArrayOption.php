<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class ArrayOption extends BaseOption
{
    public function key(): string
    {
        return 'array';
    }

    public function default(): mixed
    {
        return [];
    }

    public function castAs(): string|CastsAttributes
    {
        return 'array';
    }
}
