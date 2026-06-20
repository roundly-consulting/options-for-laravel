<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\BaseOption;

class AdminOnlyOption extends BaseOption
{
    /**
     * @var array{read: bool, write: bool, user: ?Authenticatable, owner: ?Model}|null
     */
    public static ?array $lastArgs = null;

    public function key(): string
    {
        return 'admin-only';
    }

    public function default(): mixed
    {
        return 'default';
    }

    public function authorizeRead(?Authenticatable $user, ?Model $owner): bool
    {
        self::$lastArgs = ['read' => true, 'write' => false, 'user' => $user, 'owner' => $owner];

        return (bool) ($user?->getAuthIdentifier() !== null);
    }

    public function authorizeWrite(?Authenticatable $user, ?Model $owner): bool
    {
        self::$lastArgs = ['read' => false, 'write' => true, 'user' => $user, 'owner' => $owner];

        return (bool) ($user?->getAuthIdentifier() !== null);
    }
}
