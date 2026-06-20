<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class ThemeOption extends BaseOption
{
    public function key(): string
    {
        return 'theme';
    }

    public function default(): mixed
    {
        return 'light';
    }
}
