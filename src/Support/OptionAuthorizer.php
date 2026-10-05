<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\DataTransferObjects\OptionPayload;
use RoundlyConsulting\Options\Exceptions\UnauthorizedOption;
use RoundlyConsulting\Options\OptionsManager;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
        if (! $this->allowsWrite($option, $owner)) {
            throw UnauthorizedOption::write($option->key());
        }
    }

    /**
     * Whether the current user may write the option in a scope (always, when
     * enforcement is off).
     */
    public function allowsWrite(BaseOption $option, ?Model $owner): bool
    {
        if (! $this->enforcing()) {
            return true;
        }

        $user = $this->currentUser();

        return $option->authorizeWrite($user, $owner)
            && $this->passesGate('option.write', $user, $option, $owner);
    }

    /**
     * The exported payloads the current user may read — all of them when
     * enforcement is off. Like `all()`, a row whose key no registered option
     * answers for (or whose owner no longer exists) cannot be checked, so it is
     * left out.
     *
     * @param  list<OptionPayload>  $payloads
     * @return list<OptionPayload>
     */
    public function readablePayloads(array $payloads): array
    {
        if (! $this->enforcing()) {
            return $payloads;
        }

        $owners = [];

        return array_values(array_filter(
            $payloads,
            function (OptionPayload $payload) use (&$owners): bool {
                return $this->allowsPayload($payload, $owners, read: true);
            },
        ));
    }

    /**
     * Throw unless the current user may write every payload of an import. A
     * key no registered option answers for, or an owner that does not exist,
     * cannot be checked and is refused.
     *
     * @param  list<OptionPayload>  $payloads
     *
     * @throws UnauthorizedOption
     */
    public function authorizeImport(array $payloads): void
    {
        if (! $this->enforcing()) {
            return;
        }

        $owners = [];

        foreach ($payloads as $payload) {
            if (! $this->allowsPayload($payload, $owners, read: false)) {
                throw UnauthorizedOption::write($payload->key);
            }
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

        return Config::boolean('options.authorization.enabled', false);
    }

    /**
     * @param  array<string, Model|null>  $owners  owner models resolved so far, by scope
     */
    private function allowsPayload(OptionPayload $payload, array &$owners, bool $read): bool
    {
        $class = app(OptionsManager::class)->classForKey($payload->key);

        if ($class === null) {
            return false;
        }

        $owner = null;

        if ($payload->ownerType !== null) {
            $scope = $payload->ownerType.'|'.$payload->ownerId;
            $owner = array_key_exists($scope, $owners)
                ? $owners[$scope]
                : $owners[$scope] = $this->findOwner($payload->ownerType, $payload->ownerId);

            if ($owner === null) {
                return false;
            }
        }

        $option = $class::for($owner);

        if (! $option instanceof BaseOption) {
            return true;
        }

        return $read ? $this->allowsRead($option, $owner) : $this->allowsWrite($option, $owner);
    }

    private function findOwner(string $type, int|string|null $id): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($id);
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
        if (! Config::boolean('options.authorization.use_gate', false)) {
            return true;
        }

        if (! Gate::has($ability)) {
            return true;
        }

        return Gate::forUser($user)->allows($ability, [$option, $owner]);
    }
}
