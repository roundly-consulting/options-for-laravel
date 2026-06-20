<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Options\Support\Cache;

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

    public function value(): mixed
    {
        $cache = Cache::getInstance();

        if ($cache->has($fingerprint = $this->fingerprint())) {
            return $cache->get($fingerprint);
        }

        return $cache->put($fingerprint, $this->retrieveValueFromDatabase());
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

    public function set(mixed $value): void
    {
        $option = $this->getModelQuery()
            ->forOwner($this->owner)
            ->firstOrNew(['key' => $this->key()]);

        $option->whileCastingValueAs($this->castAs(), function () use ($option, $value): void {
            $option->value = $value;
            $option->save();
        });

        Cache::getInstance()->put($this->fingerprint(), $value);
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

        return $option->castValueAs($this->castAs());
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
