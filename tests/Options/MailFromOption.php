<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class MailFromOption extends BaseOption
{
    public function key(): string
    {
        return 'mail-from';
    }

    public function default(): mixed
    {
        return null;
    }
}
