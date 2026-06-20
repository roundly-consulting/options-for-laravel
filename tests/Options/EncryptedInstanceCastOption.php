<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Casts\EnumCast;

class EncryptedInstanceCastOption extends BaseOption
{
    public function key(): string
    {
        return 'encrypted-instance';
    }

    public function castAs(): string|CastsAttributes
    {
        return new EnumCast(Status::class);
    }

    public function encrypted(): bool
    {
        return true;
    }
}
