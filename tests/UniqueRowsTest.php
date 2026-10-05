<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Tests\Fixtures\Race\Barrier;
use RoundlyConsulting\Options\Tests\Models\User;
use RoundlyConsulting\Options\Tests\Options\ThemeOption;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;
use Symfony\Component\Process\Process;

beforeEach(fn () => Options::flushCache());

/**
 * Insert a row straight into the table, as a concurrent request would.
 */
function insertRawOption(string $key, string $value, ?User $owner = null): void
{
    DB::table('options')->insert([
        'key' => $key,
        'value' => $value,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
        'owner_scope' => OptionStore::scope($owner?->getMorphClass(), $owner?->getKey()),
    ]);
}

it('refuses a second row for the same global option', function (): void {
    Options::set(ThemeOption::class, 'dark');

    insertRawOption('theme', 'duplicate');
})->throws(UniqueConstraintViolationException::class);

it('refuses a second row for the same owned option', function (): void {
    $user = User::create();
    Options::set(ThemeOption::class, 'dark', $user);

    insertRawOption('theme', 'duplicate', $user);
})->throws(UniqueConstraintViolationException::class);

it('keeps one row when a concurrent first write wins the insert race', function (?bool $owned): void {
    $user = $owned ? User::create() : null;
    $raced = false;

    // Between this request's lookup (no row yet) and its insert, another one
    // commits its own: the hook runs just before the insert's savepoint opens.
    DB::connection()->beforeStartingTransaction(function () use (&$raced, $user): void {
        if (! $raced) {
            $raced = true;
            insertRawOption('theme', 'from-the-other-request', $user);
        }
    });

    Options::set(ThemeOption::class, 'dark', $user);

    expect($raced)->toBeTrue()
        ->and(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBe('dark')
        ->and(Options::get(ThemeOption::class, $user))->toBe('dark');
})->with(['global' => [false], 'owned' => [true]]);

it('reuses a forgotten row instead of adding one', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);
    Options::set(ThemeOption::class, 'blue');

    expect(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('blue');
});

it('imports over a forgotten row', function (): void {
    Options::set(ThemeOption::class, 'dark');
    Options::forget(ThemeOption::class);

    Options::import([['key' => 'theme', 'value' => 'imported']]);

    expect(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Options::get(ThemeOption::class))->toBe('imported');
});

it('re-reads the winning row with a locking read after losing the insert race', function (): void {
    // Regression (2026-10-05 chat review, C-9): the retry read was a plain consistent read.
    // Inside a transaction on MySQL (REPEATABLE READ) that read is served from the snapshot
    // taken before the winner committed, so it saw no row and rethrew the unique violation.
    // A locking read sees the latest committed row on every engine.
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        // SQLite compiles the lock away; this grammar keeps it visible as a comment.
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    }

    // setMany()'s own transaction is level 0 -> 1; the insert's savepoint is 1 -> 2. The
    // other request's row lands between this request's lookup and that savepoint.
    $raced = false;
    $connection->beforeStartingTransaction(function (Connection $connection) use (&$raced): void {
        if (! $raced && $connection->transactionLevel() === 1) {
            $raced = true;
            insertRawOption('theme', 'from-the-other-request');
        }
    });

    $selects = [];
    DB::listen(function (QueryExecuted $query) use (&$selects): void {
        if (str_starts_with(strtolower($query->sql), 'select')) {
            $selects[] = strtolower($query->sql);
        }
    });

    Options::setMany([ThemeOption::class => 'dark']);

    $locked = static fn (string $sql): bool => str_contains($sql, 'for update') || str_contains($sql, 'lock-for-update');

    expect($raced)->toBeTrue()
        ->and($selects)->toHaveCount(2)
        ->and($locked($selects[0]))->toBeFalse()
        ->and($locked($selects[1]))->toBeTrue()
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBe('dark');
});

it('lets two concurrent first batch writes on MySQL both succeed on one row', function (): void {
    // Regression (2026-10-05 chat review, C-9), end to end: two processes write the option
    // for the first time inside setMany()'s transaction, each pausing after its lookup. On
    // MySQL the loser's plain retry read saw its pre-race snapshot and rethrew 1062.
    $directory = sys_get_temp_dir().'/options-race-'.Str::random(12);
    mkdir($directory);

    $child = new Process([PHP_BINARY, __DIR__.'/Fixtures/Race/write.php', $directory, 'from-child'], timeout: 60);

    try {
        $child->start();

        (new Barrier($directory, 'parent', 'child'))->arm();

        $parent = Barrier::attempt(static fn () => Options::setMany([ThemeOption::class => 'from-parent']));

        $child->wait();

        /** @var array{ok: bool, error: string|null}|null $other */
        $other = json_decode($child->getOutput(), true);
    } finally {
        $child->stop();
        array_map(unlink(...), glob($directory.'/*') ?: []);
        rmdir($directory);
    }

    expect($other)->toBeArray('child process said: '.$child->getOutput().$child->getErrorOutput())
        ->and($parent['error'])->toBeNull()
        ->and($other['error'] ?? null)->toBeNull()
        ->and(Option::withTrashed()->where('key', 'theme')->count())->toBe(1)
        ->and(Option::query()->where('key', 'theme')->value('value'))->toBeIn(['from-parent', 'from-child']);
})->skip(fn (): bool => DriverMatrix::driver() !== 'mysql', 'needs MySQL (TESTING_DB_DRIVER=mysql)');
