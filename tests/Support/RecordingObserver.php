<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\DataTransferObjects\OptionChange;

class RecordingObserver
{
    /**
     * @var list<array{value: mixed, owner: ?Model, change: OptionChange}>
     */
    public static array $calls = [];

    public function __invoke(mixed $value, ?Model $owner, OptionChange $change): void
    {
        self::$calls[] = ['value' => $value, 'owner' => $owner, 'change' => $change];
    }
}
