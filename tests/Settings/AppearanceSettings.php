<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Settings;

use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

class AppearanceSettings extends OptionGroup
{
    public function label(): string
    {
        return 'Appearance';
    }

    public function description(): ?string
    {
        return 'Look and feel.';
    }

    /**
     * @return list<class-string<OptionInterface>>
     */
    public function options(): array
    {
        return [
            LocaleOption::class,
            ThemeOption::class,
        ];
    }
}
