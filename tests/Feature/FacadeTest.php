<?php

declare(strict_types=1);

use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\PlainInterfaceOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;

beforeEach(fn () => app(Cache::class)->flush());

it('documents its root, fakes for real and reaches every action', function (): void {
    expect(Options::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('runs the same API injected, without the facade', function (): void {
    $user = User::create();
    $manager = app(OptionsManager::class);

    $manager->for($user)->set(ThemeOption::class, 'dark');
    $json = $manager->exportJson($user);
    Option::query()->forceDelete();

    expect($manager)->toBe(Options::getFacadeRoot())
        ->and($manager->import($json))->toBe(1)
        ->and($manager->for($user)->get(ThemeOption::class))->toBe('dark')
        ->and($manager->export($user)[0])->toBeInstanceOf(OptionPayload::class);
});

it('routes option instances, handles and the trait through the manager', function (): void {
    $user = User::create();

    ThemeOption::for($user)->set('dark');
    $user->option(ThemeOption::class)->forget();
    Options::option(ThemeOption::class)->set('blue');
    Options::resolve(ThemeOption::class, $user)->set('green');

    expect(Options::for($user)->get(ThemeOption::class))->toBe('green')
        ->and(ThemeOption::for($user)->has())->toBeTrue()
        ->and(ThemeOption::for(null)->value())->toBe('blue')
        ->and(ThemeOption::for(null)->remember(fn (): string => 'never'))->toBe('blue');

    ThemeOption::for(null)->reset();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('leaves a plain OptionInterface to its own storage', function (): void {
    PlainInterfaceOption::$stored = null;

    Options::set(PlainInterfaceOption::class, 'custom');

    expect(Options::get(PlainInterfaceOption::class))->toBe('custom')
        ->and(Option::query()->count())->toBe(0)
        ->and(fn () => Options::has(PlainInterfaceOption::class))->toThrow(InvalidOptionClassName::class)
        ->and(fn () => Options::for(null)->option(PlainInterfaceOption::class))->toThrow(InvalidOptionClassName::class);
});
