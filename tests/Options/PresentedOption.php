<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

class PresentedOption extends BaseOption
{
    public function key(): string
    {
        return 'presented';
    }

    public function default(): mixed
    {
        return 'p';
    }

    public function label(): string
    {
        return 'Presented Label';
    }

    public function help(): ?string
    {
        return 'Helpful text.';
    }

    public function section(): ?string
    {
        return 'general';
    }

    public function order(): int
    {
        return 5;
    }
}
