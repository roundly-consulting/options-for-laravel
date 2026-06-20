<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Settings;

use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\PresentedOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

class OrderedSettings extends OptionGroup
{
    /**
     * @return list<class-string<OptionInterface>>
     */
    public function options(): array
    {
        return [
            PresentedOption::class,
            ThemeOption::class,
            LocaleOption::class,
        ];
    }
}
