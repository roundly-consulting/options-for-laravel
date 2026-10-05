<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Exceptions\InvalidOwnerModel;

trait ResolvesOwner
{
    /**
     * Resolve the --owner/--owner-id options to a model instance, or null for
     * the global scope.
     */
    protected function resolveOwner(): ?Model
    {
        /** @var string|null $class */
        $class = $this->option('owner');
        /** @var string|null $id */
        $id = $this->option('owner-id');

        if ($class === null) {
            return null;
        }

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw InvalidOwnerModel::for($class);
        }

        if ($id === null) {
            throw InvalidOwnerModel::for($class.' (missing --owner-id)');
        }

        /** @var Model|null $owner */
        $owner = $class::query()->find($id);

        return $owner ?? throw InvalidOwnerModel::for("{$class} (no record with --owner-id={$id})");
    }
}
