<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Groups;

use RoundlyConsulting\Options\OptionInterface;

abstract class OptionGroup
{
    /**
     * The group's machine key (defaults to a kebab-cased class basename).
     */
    public function key(): string
    {
        return str(class_basename($this))->kebab()->toString();
    }

    /**
     * Human label for the group/page. Defaults to a titled key.
     */
    public function label(): string
    {
        return str($this->key())
            ->replace('-', ' ')
            ->title()
            ->toString();
    }

    /**
     * Optional group description.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * The member option class-strings, in declaration order.
     *
     * @return list<class-string<OptionInterface>>
     */
    abstract public function options(): array;
}
