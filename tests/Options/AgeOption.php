<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class AgeOption extends BaseOption
{
    public function key(): string
    {
        return 'age';
    }

    /**
     * @return array<int, mixed>|string
     */
    public function rules(): array|string
    {
        return ['integer', 'min:0'];
    }
}
