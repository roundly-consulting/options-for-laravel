<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands\Concerns;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use RoundlyConsulting\Options\Exceptions\InvalidOwnerModel;
use RoundlyConsulting\Options\OptionsManager;

trait ActsAsUser
{
    /**
     * Run the option operation either bypassing authorization (default) or
     * acting as the user resolved from the --as option.
     */
    protected function withAuthorizationContext(OptionsManager $manager, Closure $callback): mixed
    {
        /** @var string|null $as */
        $as = $this->option('as');

        if ($as === null) {
            return $manager->withoutAuthorization($callback);
        }

        return $manager->actingAs($this->resolveActingUser($as), $callback);
    }

    private function resolveActingUser(string $key): Authenticatable
    {
        /** @var string|null $providerName */
        $providerName = config('auth.guards.'.config('auth.defaults.guard').'.provider');

        $provider = Auth::createUserProvider($providerName);

        $user = $provider?->retrieveById($key);

        if ($user === null) {
            throw InvalidOwnerModel::for("user [{$key}]");
        }

        return $user;
    }
}
