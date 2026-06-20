<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use RoundlyConsulting\Options\DataTransferObjects\GroupDefinition;
use RoundlyConsulting\Options\DataTransferObjects\OptionDefinition;
use RoundlyConsulting\Options\Exceptions\InvalidOptionGroup;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Groups\OptionGroup;
use RoundlyConsulting\Options\OptionInterface;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\AgeOption;
use RoundlyConsulting\Options\Tests\Options\InstanceCastOption;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Options\Tests\Settings\AppearanceSettings;
use RoundlyConsulting\Options\Tests\Settings\OrderedSettings;

beforeEach(fn () => Options::flushCache());

it('uses sensible group key/label/description defaults', function (): void {
    $group = new OrderedSettings;

    expect($group->key())->toBe('ordered-settings')
        ->and($group->label())->toBe('Ordered Settings')
        ->and($group->description())->toBeNull();
});

it('honours overridden group label and description', function (): void {
    $group = new AppearanceSettings;

    expect($group->label())->toBe('Appearance')
        ->and($group->description())->toBe('Look and feel.');
});

it('resolves a group from a class-string and from an instance', function (): void {
    expect(Options::group(AppearanceSettings::class)->options())->toHaveCount(2)
        ->and(Options::group(new AppearanceSettings)->options())->toHaveCount(2);
});

it('resolves a group from a configured short key', function (): void {
    config()->set('options.groups.appearance', AppearanceSettings::class);

    expect(Options::group('appearance')->options())->toHaveCount(2);
});

it('throws for a class that is not an option group', function (): void {
    Options::group(ThemeOption::class);
})->throws(InvalidOptionGroup::class);

it('reads all current values for the global scope', function (): void {
    Options::set(ThemeOption::class, 'dark');

    expect(Options::group(AppearanceSettings::class)->all())
        ->toBe(['locale' => 'en', 'theme' => 'dark']);
});

it('isolates owner scope in all()', function (): void {
    $user = User::query()->create();

    Options::set(ThemeOption::class, 'dark', $user);

    expect(Options::group(AppearanceSettings::class)->for($user)->all()['theme'])->toBe('dark')
        ->and(Options::group(AppearanceSettings::class)->all()['theme'])->toBe('light');
});

it('sorts definitions by order then declaration index', function (): void {
    $definition = Options::group(OrderedSettings::class)->definition();

    $keys = array_map(fn (OptionDefinition $d): string => $d->key, $definition->options);

    expect($keys)->toBe(['theme', 'locale', 'presented']);
});

it('populates definition metadata for a defaulted and a presented option', function (): void {
    $definition = Options::group(OrderedSettings::class)->definition();

    expect($definition)->toBeInstanceOf(GroupDefinition::class);

    $byKey = collect($definition->options)->keyBy(fn (OptionDefinition $d): string => $d->key);

    $presented = $byKey['presented'];
    expect($presented->label)->toBe('Presented Label')
        ->and($presented->help)->toBe('Helpful text.')
        ->and($presented->section)->toBe('general')
        ->and($presented->order)->toBe(5)
        ->and($presented->type)->toBe('string')
        ->and($presented->encrypted)->toBeFalse()
        ->and($presented->default)->toBe('p');

    $theme = $byKey['theme'];
    expect($theme->label)->toBe('Theme')
        ->and($theme->help)->toBeNull()
        ->and($theme->section)->toBeNull()
        ->and($theme->order)->toBe(0)
        ->and($theme->current)->toBe('light');
});

it('reports custom type for an instance cast option', function (): void {
    $group = new class extends OptionGroup
    {
        /** @return list<class-string<OptionInterface>> */
        public function options(): array
        {
            return [InstanceCastOption::class];
        }
    };

    $definition = Options::group($group)->definition();

    expect($definition->options[0]->type)->toBe('custom');
});

it('reflects the encrypted flag in a definition', function (): void {
    $group = new class extends OptionGroup
    {
        /** @return list<class-string<OptionInterface>> */
        public function options(): array
        {
            return [SecretOption::class];
        }
    };

    $definition = Options::group($group)->definition();

    expect($definition->options[0]->encrypted)->toBeTrue();
});

it('writes group values by option key and by class-string', function (): void {
    Options::group(AppearanceSettings::class)->set([
        'theme' => 'dark',
        LocaleOption::class => 'sk',
    ]);

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(LocaleOption::class))->toBe('sk');
});

it('validates values written through a group', function (): void {
    $group = new class extends OptionGroup
    {
        /** @return list<class-string<OptionInterface>> */
        public function options(): array
        {
            return [AgeOption::class];
        }
    };

    Options::group($group)->set(['age' => 'not-a-number']);
})->throws(ValidationException::class);

it('throws for an unknown key in group set', function (): void {
    Options::group(AppearanceSettings::class)->set(['nope' => 'x']);
})->throws(InvalidOptionGroup::class);

it('returns ordered base option instances from options()', function (): void {
    $options = Options::group(OrderedSettings::class)->options();

    expect($options[0]->key())->toBe('theme')
        ->and($options[2]->key())->toBe('presented');
});

it('builds the DTOs with readonly props', function (): void {
    $option = new OptionDefinition('k', ThemeOption::class, 'L', null, null, 0, 'string', false, 'c', 'd');
    $group = new GroupDefinition('g', 'G', null, [$option]);

    expect($group->options[0]->key)->toBe('k')
        ->and($group->key)->toBe('g');
});
