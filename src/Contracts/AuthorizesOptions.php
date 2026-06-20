<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional marker documenting the authorization hooks. BaseOption provides
 * default (allow-all) implementations, so implementing this is not required.
 */
interface AuthorizesOptions
{
    public function authorizeRead(?Authenticatable $user, ?Model $owner): bool;

    public function authorizeWrite(?Authenticatable $user, ?Model $owner): bool;
}
