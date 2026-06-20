<?php

declare(strict_types=1);

use RoundlyConsulting\Options\Tests\Options\PresentedOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

it('derives presentation defaults from readable', function (): void {
    $option = ThemeOption::for(null);

    expect($option->label())->toBe('Theme')
        ->and($option->help())->toBeNull()
        ->and($option->section())->toBeNull()
        ->and($option->order())->toBe(0);
});

it('honours overridden presentation metadata', function (): void {
    $option = PresentedOption::for(null);

    expect($option->label())->toBe('Presented Label')
        ->and($option->help())->toBe('Helpful text.')
        ->and($option->section())->toBe('general')
        ->and($option->order())->toBe(5);
});
