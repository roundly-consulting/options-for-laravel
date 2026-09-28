<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Exceptions\UnauthorizedOption;

/**
 * Central, opt-in enforcement of per-option read/write authorization.
 */
final class OptionAuthorizer
{
    private bool $bypass = false;

    private ?Authenticatable $actingAs = null;

    private bool $hasActingUser = false;

    /**
     * Enforce read authorization for an option in a scope.
     */
    public function read(BaseOption $option, ?Model $owner): void
    {
        if (! $this->allowsRead($option, $owner)) {
            throw UnauthorizedOption::read($option->key());
        }
    }

    /**
     * Whether the current user may read the option in a scope (always, when
     * enforcement is off).
     */
    public function allowsRead(BaseOption $option, ?Model $owner): bool
    {
        if (! $this->enforcing()) {
            return true;
        }

        $user = $this->currentUser();

        return $option->authorizeRead($user, $owner)
            && $this->passesGate('option.read', $user, $option, $owner);
    }

    /**
     * Enforce write authorization for an option in a scope.
     */
    public function write(BaseOption $option, ?Model $owner): void
    {
        if (! $this->enforcing()) {
            return;
        }

        $user = $this->currentUser();

        $allowed = $option->authorizeWrite($user, $owner)
            && $this->passesGate('option.write', $user, $option, $owner);

        if (! $allowed) {
            throw UnauthorizedOption::write($option->key());
        }
    }

    /**
     * Run the callback authorizing option access as the given user.
     */
    public function actingAs(?Authenticatable $user, Closure $callback): mixed
    {
        $previousUser = $this->actingAs;
        $previousHas = $this->hasActingUser;

        $this->actingAs = $user;
        $this->hasActingUser = true;

        try {
            return $callback();
        } finally {
            $this->actingAs = $previousUser;
            $this->hasActingUser = $previousHas;
        }
    }

    /**
     * Disable enforcement for the duration of the callback.
     */
    public function withoutAuthorization(Closure $callback): mixed
    {
        $previous = $this->bypass;

        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }

    /**
     * Run a callback with enforcement bypassed (no return value capture needed).
     */
    public function bypass(Closure $callback): mixed
    {
        return $this->withoutAuthorization($callback);
    }

    /**
     * Whether access is being enforced right now: enabled, and not bypassed.
     */
    public function enforcing(): bool
    {
        if ($this->bypass) {
            return false;
        }

        return (bool) config('options.authorization.enabled', false);
    }

    private function currentUser(): ?Authenticatable
    {
        if ($this->hasActingUser) {
            return $this->actingAs;
        }

        return Auth::user();
    }

    private function passesGate(string $ability, ?Authenticatable $user, BaseOption $option, ?Model $owner): bool
    {
        if (! config('options.authorization.use_gate', false)) {
            return true;
        }

        if (! Gate::has($ability)) {
            return true;
        }

        return Gate::forUser($user)->allows($ability, [$option, $owner]);
    }
}
