<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use RoundlyConsulting\Options\Option;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing stored options from `options.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class OptionModel
{
    /**
     * @return class-string<Option>
     */
    public static function class(): string
    {
        return ModelResolver::for('options.model', Option::class);
    }

    /**
     * @internal runs the callback once a write on the option model's connection is
     * durable: right away outside a transaction, after the outermost commit inside
     * one, never on rollback
     */
    public static function afterCommit(Closure $callback): void
    {
        $model = self::class();
        $connection = (new $model)->getConnection();

        if ($connection->transactionLevel() === 0) {
            $callback();

            return;
        }

        $connection->afterCommit($callback);
    }
}
