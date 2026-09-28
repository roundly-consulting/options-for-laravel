<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class EncryptedIntegerOption extends BaseOption
{
    public function key(): string
    {
        return 'encrypted-integer';
    }

    public function castAs(): string|CastsAttributes
    {
        return 'integer';
    }

    public function encrypted(): bool
    {
        return true;
    }
}
