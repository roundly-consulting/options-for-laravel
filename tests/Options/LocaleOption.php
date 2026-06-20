<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class LocaleOption extends BaseOption
{
    public function key(): string
    {
        return 'locale';
    }

    public function default(): mixed
    {
        return 'en';
    }
}
