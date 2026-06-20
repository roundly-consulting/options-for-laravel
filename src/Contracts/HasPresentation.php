<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Contracts;

/**
 * Optional marker documenting the presentation metadata hooks. BaseOption
 * provides sensible defaults, so implementing this is not required.
 */
interface HasPresentation
{
    public function label(): string;

    public function help(): ?string;

    public function section(): ?string;

    public function order(): int;
}
