<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Options\Casts\EncryptedCast;
use RoundlyConsulting\Options\Enums\OptionChangeType;
use RoundlyConsulting\Options\Events\OptionForgotten;
use RoundlyConsulting\Options\Events\OptionResolved;
use RoundlyConsulting\Options\Events\OptionSet;
use RoundlyConsulting\Options\Exceptions\EncryptionNotSupported;
use RoundlyConsulting\Options\Support\Cache;
use RoundlyConsulting\Options\Support\ConfigBridge;
use RoundlyConsulting\Options\Support\OptionAuthorizer;
use RoundlyConsulting\Options\Support\OptionModel;
use RoundlyConsulting\Options\Support\OptionObservers;
use RoundlyConsulting\Options\Support\OptionStore;
use RoundlyConsulting\Options\Support\StoredValue;
use RoundlyConsulting\Options\Support\ValueCaster;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
        $this->assertWritable($value);

        $stored = new StoredValue(true, $this->serializeValue($value));

        $this->persist($stored->raw);

        // Cache what a read of the row returns, so the next get() runs the cast
        // and yields the same type it would after the cache expires. This request
        // reads its own write at once (inside a transaction too, as the database
        // shows it); other processes and the rest of the app only once it commits.
        app(Cache::class)->put($this->fingerprint(), $stored);
        $this->store()->forget($this->fingerprint());

        $this->afterCommit(function () use ($stored): void {
            $this->store()->put($this->fingerprint(), $stored);

            $this->announce(OptionChangeType::Set, $this->hydrate($stored));
        });
    }

    /**
     * @internal the checks every write runs — authorization, then `rules()` — without writing
     */
    final public function assertWritable(mixed $value): void
    {
        $this->guardWrite();

        $this->validate($value);
    }

    /**
     * @internal drops this scope's memoised and persistently cached value
     */
    final public function forgetCachedValue(): void
    {
        app(Cache::class)->forget($this->fingerprint());
        $this->store()->forget($this->fingerprint());
    }

    /**
     * @internal runs `rules()` against a value, as every write does; throws a ValidationException
     */
    final public function validateValue(mixed $value): void
    {
        $this->validate($value);
    }

    /**
     * @internal the raw column string a write stores for the value (ciphertext when encrypted)
     */
    final public function serializeValue(mixed $value): ?string
    {
        return ValueCaster::serialize($this->castingModel(), 'value', $this->resolveCast(), $value);
    }

    /**
     * @internal the cast value a read returns for a raw column string
     */
    final public function castStoredValue(?string $raw): mixed
    {
        return $this->hydrate(new StoredValue(true, $raw));
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

        app(Cache::class)->put($this->fingerprint(), StoredValue::missing());
        $this->store()->forget($this->fingerprint());

        $this->afterCommit(fn () => $this->announce(OptionChangeType::Forgotten, null));
    }

    /**
     * Run the callback once the write is durable: right away outside a
     * transaction, after the outermost commit inside one, never on rollback.
     */
    private function afterCommit(Closure $callback): void
    {
        $connection = $this->castingModel()->getConnection();

        if ($connection->transactionLevel() === 0) {
            $callback();

            return;
        }

        $connection->afterCommit($callback);
    }

    /**
     * Tell the rest of the app about a change: the option's observers and the
     * live config bridge always, the `OptionSet` / `OptionForgotten` events
     * when enabled. Observers and the bridge are called directly, so turning
     * events off — or a host test's `Event::fake()` — does not silence them.
     */
    private function announce(OptionChangeType $type, mixed $value): void
    {
        app(OptionObservers::class)->dispatch($this->key(), $type, $value, $this->owner);

        rescue(fn () => app(ConfigBridge::class)->syncOptionKey($this->key()), report: false);

        if (! $this->eventsEnabled()) {
            return;
        }

        match ($type) {
            OptionChangeType::Set => OptionSet::dispatch($this->key(), $value, $this->owner),
            OptionChangeType::Forgotten => OptionForgotten::dispatch($this->key(), $this->owner),
        };
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
     * Upsert this scope's row. The unique (owner_scope, key) index settles a
     * race between two first writes: the loser's insert fails and it updates
     * the winner's row instead. A forgotten (soft-deleted) row is reused.
     *
     * The loser re-reads with a locking read: inside a transaction, a plain read
     * on MySQL (REPEATABLE READ) is served from the snapshot taken before the
     * winner committed and would not see its row.
     */
    private function persist(?string $raw): void
    {
        try {
            $this->saveRow($this->storedRow() ?? $this->newRow(), $raw);
        } catch (UniqueConstraintViolationException $exception) {
            $this->saveRow($this->storedRow(lock: true) ?? throw $exception, $raw);
        }
    }

    private function saveRow(Option $option, ?string $raw): void
    {
        $option->value = $raw;
        $option->{$option->getDeletedAtColumn()} = null;

        if ($option->exists) {
            $option->save();

            return;
        }

        // Insert under a savepoint, so a lost race leaves an enclosing
        // transaction usable (Postgres aborts it on any failed statement).
        $option->getConnection()->transaction(fn (): bool => $option->save());
    }

    private function storedRow(bool $lock = false): ?Option
    {
        return $this->getModelQuery()
            ->withTrashed()
            ->forOwner($this->owner)
            ->where('key', $this->key())
            ->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())
            ->first();
    }

    private function newRow(): Option
    {
        $model = OptionModel::class();
        $option = new $model;
        $option->key = $this->key();

        if (! is_null($this->owner)) {
            $option->owner()->associate($this->owner);
        }

        return $option;
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

        if (! Config::boolean('options.events.resolved', false)) {
            return;
        }

        OptionResolved::dispatch($this->key(), $value, $this->owner);
    }

    protected function eventsEnabled(): bool
    {
        return Config::boolean('options.events.enabled', true);
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
