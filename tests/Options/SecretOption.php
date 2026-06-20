<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class SecretOption extends BaseOption
{
    public function key(): string
    {
        return 'secret';
    }

    public function encrypted(): bool
    {
        return true;
    }
}
