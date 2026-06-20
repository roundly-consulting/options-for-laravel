<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Options\Casts\EncryptedCast;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionResolved;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Exceptions\EncryptionNotSupported;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionStore;

/**
 * @phpstan-consistent-constructor
 */
abstract class BaseOption implements OptionInterface
{
    public function __construct(protected ?Model $owner = null) {}

    public static function for(?Model $owner): static
    {
        return new static($owner);
    }

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    public function key(): string
    {
        return class_basename($this);
    }

    public function readable(): string
    {
        return str($this->key())
            ->kebab()
            ->title()
            ->replace('-', ' ')
            ->toString();
    }

    /**
     * Human label for a settings UI. Defaults to readable().
     */
    public function label(): string
    {
        return $this->readable();
    }

    /**
     * Optional help/description text shown under the field.
     */
    public function help(): ?string
    {
        return null;
    }

    /**
     * Section/tab a UI may group the field under. Null = ungrouped.
     */
    public function section(): ?string
    {
        return null;
    }

    /**
     * Sort order within the group/section (ascending).
     */
    public function order(): int
    {
        return 0;
    }

    /**
     * May the given user read this option in this scope? Default: allowed.
     */
    public function authorizeRead(?Authenticatable $user, ?Model $owner): bool
    {
        return true;
    }

    /**
     * May the given user write this option in this scope? Default: allowed.
     */
    public function authorizeWrite(?Authenticatable $user, ?Model $owner): bool
    {
        return true;
    }

    public function value(): mixed
    {
        $this->guardRead();

        $cache = Cache::getInstance();
        $fingerprint = $this->fingerprint();

        if ($cache->has($fingerprint)) {
            $value = $cache->get($fingerprint);
        } else {
            $value = $this->store()->remember(
                $fingerprint,
                fn (): mixed => $this->retrieveValueFromDatabase(),
            );

            $cache->put($fingerprint, $value);
        }

        $this->dispatchResolved($value);

        return $value;
    }

    public function default(): mixed
    {
        return null;
    }

    /**
     * @return CastsAttributes<mixed, mixed>|string
     */
    public function castAs(): string|CastsAttributes
    {
        return 'string';
    }

    /**
     * Whether the value should be encrypted at rest.
     */
    public function encrypted(): bool
    {
        return false;
    }

    /**
     * Laravel validation rules applied to the value on set(). Empty = no validation.
     *
     * @return array<int, mixed>|string
     */
    public function rules(): array|string
    {
        return [];
    }

    public function set(mixed $value): void
    {
        $this->guardWrite();

        $this->validate($value);

        $cast = $this->resolveCast();

        $option = $this->getModelQuery()
            ->forOwner($this->owner)
            ->firstOrNew(['key' => $this->key()]);

        if (! $option->exists && ! is_null($this->owner)) {
            $option->owner()->associate($this->owner);
        }

        $option->whileCastingValueAs($cast, function () use ($option, $value): void {
            $option->value = $value;
            $option->save();
        });

        Cache::getInstance()->put($this->fingerprint(), $value);
        $this->store()->put($this->fingerprint(), $value);

        if ($this->eventsEnabled()) {
            OptionSet::dispatch($this->key(), $value, $this->owner);
        }
    }

    public function has(): bool
    {
        $this->guardRead();

        return $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->exists();
    }

    public function forget(): void
    {
        $this->guardWrite();

        $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->get()
            ->each(fn (Option $option) => $option->delete());

        Cache::getInstance()->forget($this->fingerprint());
        $this->store()->forget($this->fingerprint());

        if ($this->eventsEnabled()) {
            OptionForgotten::dispatch($this->key(), $this->owner);
        }
    }

    public function reset(): void
    {
        $this->forget();
    }

    public function remember(Closure $callback): mixed
    {
        if ($this->has()) {
            return $this->value();
        }

        $value = $callback();

        $this->set($value);

        return $value;
    }

    protected function guardRead(): void
    {
        app(OptionAuthorizer::class)->read($this, $this->owner);
    }

    protected function guardWrite(): void
    {
        app(OptionAuthorizer::class)->write($this, $this->owner);
    }

    protected function validate(mixed $value): void
    {
        $rules = $this->rules();

        if ($rules === [] || $rules === '') {
            return;
        }

        Validator::make(['value' => $value], ['value' => $rules])->validate();
    }

    /**
     * @return CastsAttributes<mixed, mixed>|string
     */
    protected function resolveCast(): string|CastsAttributes
    {
        if (! $this->encrypted()) {
            return $this->castAs();
        }

        $inner = $this->castAs();

        if (! is_string($inner)) {
            throw EncryptionNotSupported::forCastInstance(static::class);
        }

        return EncryptedCast::class.':'.$inner;
    }

    protected function retrieveValueFromDatabase(): mixed
    {
        $option = $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->first();

        if (is_null($option)) {
            return $this->default();
        }

        return $option->castValueAs($this->resolveCast());
    }

    protected function dispatchResolved(mixed $value): void
    {
        if (! $this->eventsEnabled()) {
            return;
        }

        if (! config('options.events.resolved', false)) {
            return;
        }

        OptionResolved::dispatch($this->key(), $value, $this->owner);
    }

    protected function eventsEnabled(): bool
    {
        return (bool) config('options.events.enabled', true);
    }

    protected function store(): OptionStore
    {
        return app(OptionStore::class);
    }

    protected function fingerprint(): string
    {
        $ownerIdentifier = 'global';

        if (! is_null($this->owner)) {
            $ownerIdentifier = md5($this->owner->getMorphClass().$this->owner->getKey());
        }

        return "options:{$this->key()}:{$ownerIdentifier}";
    }

    /**
     * @return Builder<Option>
     */
    protected function getModelQuery(): Builder
    {
        /** @var class-string<Option> $model */
        $model = config('options.model', Option::class);

        return $model::query();
    }
}
