<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class EncryptedCollectionOption extends BaseOption
{
    public function key(): string
    {
        return 'encrypted-collection';
    }

    public function castAs(): string|CastsAttributes
    {
        return 'collection';
    }

    public function encrypted(): bool
    {
        return true;
    }
}
