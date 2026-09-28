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
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;
use RoundlyConsulting\Options\Support\ValueCaster;

/**
 * A setting. Extend it and override the hooks (`key`, `default`, `castAs`,
 * `encrypted`, `rules`, `authorizeRead/Write`, presentation). The value
 * operations (`value`, `set`, `has`, `forget`, `reset`, `remember`) are final
 * and go through the bound `OptionsManager`, so `Options::fake()` sees calls
 * made on an option instance too.
 *
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

    /**
     * The current value — read through the (possibly faked) options manager.
     */
    final public function value(): mixed
    {
        return self::manager()->get(static::class, $this->owner);
    }

    /**
     * @internal the storage read behind `Options::get()`; call `value()` instead
     */
    final public function loadValue(): mixed
    {
        $this->guardRead();

        $value = $this->hydrate($this->storedValue());

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

    /**
     * Persist a new value — written through the (possibly faked) options manager.
     */
    final public function set(mixed $value): void
    {
        self::manager()->set(static::class, $value, $this->owner);
    }

    /**
     * Whether a value is stored in this scope.
     */
    final public function has(): bool
    {
        return self::manager()->has(static::class, $this->owner);
    }

    /**
     * Delete the stored value, reverting to the default.
     */
    final public function forget(): void
    {
        self::manager()->forget(static::class, $this->owner);
    }

    /**
     * Alias of forget().
     */
    final public function reset(): void
    {
        self::manager()->reset(static::class, $this->owner);
    }

    /**
     * The stored value, or store and return the callback's result when unset.
     */
    final public function remember(Closure $callback): mixed
    {
        return self::manager()->remember(static::class, $callback, $this->owner);
    }

    /**
     * @internal the storage write behind `Options::set()`; call `set()` instead
     */
    final public function storeValue(mixed $value): void
    {
        $this->guardWrite();

        $this->validate($value);

        $stored = new StoredValue(true, ValueCaster::serialize($this->castingModel(), 'value', $this->resolveCast(), $value));

        $option = $this->getModelQuery()
            ->forOwner($this->owner)
            ->firstOrNew(['key' => $this->key()]);

        if (! $option->exists && ! is_null($this->owner)) {
            $option->owner()->associate($this->owner);
        }

        $option->value = $stored->raw;
        $option->save();

        // Cache what a read of the row returns, so the next get() runs the cast
        // and yields the same type it would after the cache expires.
        app(Cache::class)->put($this->fingerprint(), $stored);
        $this->store()->put($this->fingerprint(), $stored);

        if ($this->eventsEnabled()) {
            OptionSet::dispatch($this->key(), $this->hydrate($stored), $this->owner);
        }
    }

    /**
     * @internal the storage check behind `Options::has()`; call `has()` instead
     */
    final public function isStored(): bool
    {
        $this->guardRead();

        return $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->exists();
    }

    /**
     * @internal the storage delete behind `Options::forget()`; call `forget()` instead
     */
    final public function deleteValue(): void
    {
        $this->guardWrite();

        $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->get()
            ->each(fn (Option $option) => $option->delete());

        app(Cache::class)->forget($this->fingerprint());
        $this->store()->forget($this->fingerprint());

        if ($this->eventsEnabled()) {
            OptionForgotten::dispatch($this->key(), $this->owner);
        }
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

    /**
     * The scope's stored value: from the in-request memo, else the persistent
     * cache, else the database.
     */
    private function storedValue(): StoredValue
    {
        $memo = app(Cache::class);
        $fingerprint = $this->fingerprint();

        $memoized = $memo->has($fingerprint) ? $memo->get($fingerprint) : null;

        if ($memoized instanceof StoredValue) {
            return $memoized;
        }

        $stored = $this->store()->remember($fingerprint, fn (): StoredValue => $this->readStoredValue());

        $memo->put($fingerprint, $stored);

        return $stored;
    }

    private function readStoredValue(): StoredValue
    {
        $option = $this->getModelQuery()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->first();

        if (is_null($option)) {
            return StoredValue::missing();
        }

        $raw = $option->getAttributes()['value'] ?? null;

        return new StoredValue(true, is_scalar($raw) ? (string) $raw : null);
    }

    /**
     * The cast value for what storage holds; the default when nothing is stored.
     */
    private function hydrate(StoredValue $stored): mixed
    {
        if (! $stored->exists) {
            return $this->default();
        }

        return ValueCaster::hydrate($this->castingModel(), 'value', $this->resolveCast(), $stored->raw);
    }

    /**
     * An unsaved instance of the configured option model to run casts on.
     */
    private function castingModel(): Option
    {
        $model = OptionModel::class();

        return new $model;
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
        return OptionStore::fingerprint($this->key(), $this->owner?->getMorphClass(), $this->owner?->getKey());
    }

    /**
     * @return Builder<Option>
     */
    protected function getModelQuery(): Builder
    {
        $model = OptionModel::class();

        return $model::query();
    }

    private static function manager(): OptionsManager
    {
        return app(OptionsManager::class);
    }
}
