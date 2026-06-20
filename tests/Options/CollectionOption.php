<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use RoundlyConsulting\Options\BaseOption;

class CollectionOption extends BaseOption
{
    public function key(): string
    {
        return 'custom-key';
    }

    public function readable(): string
    {
        return 'Custom collection option';
    }

    public function default(): mixed
    {
        return collect([
            'default' => 'yes',
        ]);
    }

    public function castAs(): string|CastsAttributes
    {
        return 'collection';
    }
}
