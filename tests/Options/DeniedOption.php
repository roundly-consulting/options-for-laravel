<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Tests\Options;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\BaseOption;

class DeniedOption extends BaseOption
{
    public function key(): string
    {
        return 'denied';
    }

    public function authorizeRead(?Authenticatable $user, ?Model $owner): bool
    {
        return false;
    }

    public function authorizeWrite(?Authenticatable $user, ?Model $owner): bool
    {
        return false;
    }
}
