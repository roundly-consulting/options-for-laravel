<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * The README's usage example — ValidatedOptionTest pins its rules to the README.
 */
class ReadmeThemeOption extends BaseOption
{
    public function key(): string
    {
        return 'theme';
    }

    public function default(): mixed
    {
        return 'light';
    }

    /**
     * @return array<int, mixed>|string
     */
    public function rules(): array|string
    {
        return ['required', 'in:light,dark'];
    }
}
