<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\InvalidOptionClassName;
use RoundlyConsulting\Options\Exceptions\InvalidOptionPayload;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Testing\OptionsFake;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\AgeOption;
use RoundlyConsulting\Options\Tests\Options\CollectionOption;
use RoundlyConsulting\Options\Tests\Options\EncryptedIntegerOption;
use RoundlyConsulting\Options\Tests\Options\LocaleOption;
use RoundlyConsulting\Options\Tests\Options\PlainInterfaceOption;
use RoundlyConsulting\Options\Tests\Options\SecretOption;
use RoundlyConsulting\Options\Tests\Options\Status;
use RoundlyConsulting\Options\Tests\Options\StatusOption;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Options\Tests\Settings\AppearanceSettings;

beforeEach(fn () => app(Cache::class)->flush());

it('does not write to the database', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');

    expect(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Option::query()->count())->toBe(0);
});

it('returns the default for an unset option', function (): void {
    Options::fake();

    expect(Options::get(ThemeOption::class))->toBe('light');
});

it('asserts an option was set', function (): void {
    $fake = Options::fake();

    Options::set(ThemeOption::class, 'dark');

    $fake->assertSet(ThemeOption::class);
    $fake->assertSet(ThemeOption::class, 'dark');
});

it('fails assertSet when nothing was set', function (): void {
    $fake = Options::fake();

    expect(fn () => $fake->assertSet(ThemeOption::class))
        ->toThrow(AssertionFailedError::class);
});

it('asserts nothing was set', function (): void {
    $fake = Options::fake();

    $fake->assertNothingSet();

    Options::set(ThemeOption::class, 'dark');

    expect(fn () => $fake->assertNothingSet())->toThrow(AssertionFailedError::class);
});

it('tracks has and forget in memory', function (): void {
    $fake = Options::fake();

    expect(Options::has(ThemeOption::class))->toBeFalse();

    Options::set(ThemeOption::class, 'dark');

    expect(Options::has(ThemeOption::class))->toBeTrue();

    Options::forget(ThemeOption::class);

    expect(Options::has(ThemeOption::class))->toBeFalse();

    $fake->assertForgotten(ThemeOption::class);
});

it('resets and remembers in memory', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');
    Options::reset(ThemeOption::class);

    expect(Options::get(ThemeOption::class))->toBe('light');

    $value = Options::remember(ThemeOption::class, fn (): string => 'computed');

    expect($value)->toBe('computed')
        ->and(Options::get(ThemeOption::class))->toBe('computed');
});

it('scopes fake values per owner', function (): void {
    $fake = Options::fake();
    $user = User::create();

    Options::for($user)->set(ThemeOption::class, 'dark');

    expect(Options::for($user)->get(ThemeOption::class))->toBe('dark')
        ->and(Options::get(ThemeOption::class))->toBe('light')
        ->and(Options::for($user)->all()->get('theme'))->toBe('dark');

    $fake->assertSet(ThemeOption::class, 'dark', $user);
});

it('flushes the fake cache without error', function (): void {
    $fake = Options::fake();

    $fake->flushCache();

    expect(true)->toBeTrue();
});

it('remember returns the existing value without calling the closure', function (): void {
    Options::fake();

    Options::set(ThemeOption::class, 'dark');

    $called = false;

    $value = Options::remember(ThemeOption::class, function () use (&$called): string {
        $called = true;

        return 'computed';
    });

    expect($value)->toBe('dark')
        ->and($called)->toBeFalse();
});

it('does not match assertSet across different keys or owners', function (): void {
    $fake = Options::fake();
    $user = User::create();

    Options::set(ThemeOption::class, 'dark');

    expect(fn () => $fake->assertSet(ThemeOption::class, 'dark', $user))
        ->toThrow(AssertionFailedError::class);
});

it('fails assertForgotten when nothing was forgotten', function (): void {
    $fake = Options::fake();

    expect(fn () => $fake->assertForgotten(ThemeOption::class))
        ->toThrow(AssertionFailedError::class);
});

describe('no database under the fake', function (): void {
    it('keeps every entry point off the database', function (Closure $write): void {
        $fake = Options::fake();
        $user = User::create();

        $write($user);

        expect(Option::query()->count())->toBe(0);
        $fake->assertSet(ThemeOption::class, 'dark', $user);
    })->with([
        'option instance' => [fn (User $user) => ThemeOption::for($user)->set('dark')],
        'HasOptions trait' => [fn (User $user) => $user->option(ThemeOption::class)->set('dark')],
        'resolve()' => [fn (User $user) => Options::resolve(ThemeOption::class, $user)->set('dark')],
        'for()->option()' => [fn (User $user) => Options::for($user)->option(ThemeOption::class)->set('dark')],
        'for()->key()' => [function (User $user): void {
            Options::register(['theme' => ThemeOption::class]);
            Options::for($user)->key('theme')->set('dark');
        }],
        'group()->set()' => [fn (User $user) => Options::group(AppearanceSettings::class, $user)->set(['theme' => 'dark'])],
        'remember()' => [fn (User $user) => Options::for($user)->remember(ThemeOption::class, fn (): string => 'dark')],
        'setMany()' => [fn (User $user) => Options::for($user)->setMany([ThemeOption::class => 'dark'])],
    ]);

    it('reads option(), key(), group() and instances from memory', function (): void {
        Options::set(ThemeOption::class, 'stored');
        Options::fake();
        Options::register(['theme' => ThemeOption::class, 'locale' => LocaleOption::class]);

        Options::option(ThemeOption::class)->set('dark');
        Options::key('locale')->set('sk');

        expect(Options::option(ThemeOption::class)->get())->toBe('dark')
            ->and(Options::key('theme')->value())->toBe('dark')
            ->and(Options::option(ThemeOption::class)->has())->toBeTrue()
            ->and(ThemeOption::for(null)->value())->toBe('dark')
            ->and(Options::resolve(ThemeOption::class)->value())->toBe('dark')
            ->and(Options::group(AppearanceSettings::class)->all())->toBe(['locale' => 'sk', 'theme' => 'dark'])
            ->and(Options::group(AppearanceSettings::class)->definition()->options[1]->current)->toBe('dark');

        Options::option(ThemeOption::class)->forget();

        expect(Options::option(ThemeOption::class)->get())->toBe('light')
            ->and(Option::query()->value('value'))->toBe('stored');
    });
});

it('asserts forgets, including through the trait', function (): void {
    $fake = Options::fake();
    $user = User::create();

    $fake->assertNothingForgotten();
    $user->option(ThemeOption::class)->forget();

    $fake->assertForgotten(ThemeOption::class, $user);

    expect(fn () => $fake->assertForgotten(ThemeOption::class))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingForgotten())->toThrow(AssertionFailedError::class);
});

it('asserts sets made through the trait', function (): void {
    $fake = Options::fake();
    $user = User::create();

    $user->option(ThemeOption::class)->set('dark');

    Options::assertSet(ThemeOption::class, 'dark', $user);

    expect(fn () => $fake->assertSet(ThemeOption::class, 'light', $user))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingSet())->toThrow(AssertionFailedError::class);
});

it('imports into memory and exports from it', function (): void {
    $fake = Options::fake();
    $user = User::create();

    $fake->assertNothingImported();
    $count = Options::import([
        ['key' => 'theme', 'value' => 'dark'],
        ['key' => 'theme', 'value' => 'blue', 'owner_type' => $user->getMorphClass(), 'owner_id' => $user->getKey()],
    ]);
    Options::import(json_encode([['key' => 'locale', 'value' => 'sk']]));

    expect($count)->toBe(2)
        ->and(Option::query()->count())->toBe(0)
        ->and(Options::get(ThemeOption::class))->toBe('dark')
        ->and(Options::for($user)->get(ThemeOption::class))->toBe('blue')
        ->and(Options::export())->toEqual([
            new OptionPayload('theme', 'dark'),
            new OptionPayload('locale', 'sk'),
            new OptionPayload('theme', 'blue', $user->getMorphClass(), $user->getKey()),
        ])
        ->and(Options::export(globalOnly: true))->toHaveCount(2)
        ->and(Options::for($user)->export())->toEqual([new OptionPayload('theme', 'blue', $user->getMorphClass(), $user->getKey())])
        ->and(json_decode(Options::exportJson($user), true)[0]['owner_id'])->toBe($user->getKey());

    $fake->assertImported();
    $fake->assertImported(fn (array $payloads): bool => $payloads[0]->key === 'locale');

    expect(fn () => $fake->assertImported(fn (array $payloads): bool => count($payloads) === 5))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingImported())->toThrow(AssertionFailedError::class);
});

it('fails assertImported when nothing was imported', function (): void {
    $fake = Options::fake();

    expect(fn () => $fake->assertImported())->toThrow(AssertionFailedError::class);
});

it('is the manager subtype the container resolves', function (): void {
    $fake = Options::fake();

    expect($fake)->toBeInstanceOf(OptionsFake::class)
        ->and(app(OptionsManager::class))->toBe($fake)
        ->and(options())->toBe($fake);
});

it('validates writes like the real manager', function (): void {
    $fake = Options::fake();

    expect(fn () => Options::set(AgeOption::class, 'abc'))->toThrow(ValidationException::class)
        ->and(Options::has(AgeOption::class))->toBeFalse();

    $fake->assertNothingSet();
});

it('returns the cast value like the real manager', function (): void {
    Options::fake();

    Options::set(StatusOption::class, 'active');
    Options::set(CollectionOption::class, ['email' => false]);
    Options::set(EncryptedIntegerOption::class, '5');

    expect(Options::get(StatusOption::class))->toBe(Status::Active)
        ->and(Options::get(CollectionOption::class))->toBeInstanceOf(Collection::class)
        ->and(Options::get(CollectionOption::class)?->get('email'))->toBeFalse()
        ->and(Options::get(EncryptedIntegerOption::class))->toBe(5);
});

it('exports what the database would hold, ciphertext included', function (): void {
    Options::fake();

    Options::set(SecretOption::class, 'sk_live_TOPSECRET');

    $exported = Options::export()[0]->value;

    expect($exported)->not->toContain('TOPSECRET')
        ->and(Crypt::decryptString($exported))->toBe('sk_live_TOPSECRET')
        ->and(Options::get(SecretOption::class))->toBe('sk_live_TOPSECRET');
});

it('keeps the keys registered before it was installed', function (): void {
    Options::register(['theme' => ThemeOption::class]);

    Options::fake();
    Options::set('theme', 'dark');

    expect(Options::get('theme'))->toBe('dark')
        ->and(Options::registered())->toHaveKey('theme');
});

it('observes keys registered after it was installed', function (): void {
    Options::fake();
    Options::register(['theme' => ThemeOption::class]);

    $seen = [];
    Options::observe('theme', function (mixed $value) use (&$seen): void {
        $seen[] = $value;
    });

    Options::set('theme', 'dark');

    expect($seen)->toBe(['dark']);
});

it('refuses a non-scalar imported value, like the real manager', function (): void {
    // Regression (2026-10-05 chat review, C-8): the fake imported it and returned it as
    // given, where the real import fails. This replaces a test that pinned the old behaviour.
    $fake = Options::fake();

    expect(fn () => Options::import([['key' => 'status', 'value' => ['not' => 'a column value']]]))
        ->toThrow(InvalidOptionPayload::class);

    $fake->assertNothingImported();

    expect(Options::get(StatusOption::class))->toBe(Status::Inactive);
});

it('refuses has() and forget() on a plain OptionInterface, like the real manager', function (): void {
    // Regression (2026-10-05 chat review, C-15): the fake answered false and recorded the
    // forget, where the real manager throws InvalidOptionClassName.
    $fake = Options::fake();

    expect(fn () => Options::has(PlainInterfaceOption::class))->toThrow(InvalidOptionClassName::class)
        ->and(fn () => Options::forget(PlainInterfaceOption::class))->toThrow(InvalidOptionClassName::class);

    $fake->assertNothingForgotten();
});

it('returns the stored value to both racing first remember() calls, like the real manager', function (): void {
    $fake = Options::fake();

    $inner = null;
    $outer = Options::remember(ThemeOption::class, function () use (&$inner): string {
        $inner = Options::remember(ThemeOption::class, fn (): string => 'from-b');

        return 'from-a';
    });

    expect($inner)->toBe('from-b')
        ->and($outer)->toBe('from-b')
        ->and(Options::get(ThemeOption::class))->toBe('from-b');

    $fake->assertSet(ThemeOption::class, 'from-b');

    expect(fn () => $fake->assertSet(ThemeOption::class, 'from-a'))->toThrow(AssertionFailedError::class);
});
