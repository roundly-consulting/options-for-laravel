<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Fixtures\Race;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A two-process rendezvous on an option write: each side stops right after its first read
 * of the `options` table, until the other side has made the same read. Both writers have
 * then looked for the row (and, inside a transaction, taken their snapshot) before either
 * inserts — the interleaving a real race only hits by luck, made deterministic.
 *
 * One side runs in the test process, the other in `write.php`; they meet through marker
 * files in a shared directory.
 */
final class Barrier
{
    private bool $passed = false;

    public function __construct(
        private readonly string $directory,
        private readonly string $self,
        private readonly string $other,
        private readonly int $timeoutSeconds = 20,
    ) {}

    public function arm(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if ($this->passed || preg_match('/^select .* from [`"]?options[`"]?/i', $query->sql) !== 1) {
                return;
            }

            $this->passed = true;

            touch($this->directory.'/'.$this->self);

            $deadline = microtime(true) + $this->timeoutSeconds;

            while (! file_exists($this->directory.'/'.$this->other)) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException("Barrier timed out waiting for [{$this->other}].");
                }

                usleep(10_000);
            }
        });
    }

    /**
     * Run the write and report its outcome as plain data, so both processes report alike.
     *
     * @param  Closure(): void  $write
     * @return array{ok: bool, error: string|null}
     */
    public static function attempt(Closure $write): array
    {
        try {
            $write();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception::class.': '.$exception->getMessage()];
        }
    }
}
