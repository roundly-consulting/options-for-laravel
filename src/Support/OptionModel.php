<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use RoundlyConsulting\Options\Option;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing stored options from `options.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not an Option (so it cannot answer the
 * package's `forOwner` scope or value casts) falls back to the packaged model.
 */
final class OptionModel
{
    /**
     * @return class-string<Option>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('options.model', Option::class);

        return is_a($model, Option::class, true) ? $model : Option::class;
    }
}
