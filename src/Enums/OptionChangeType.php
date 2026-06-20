<?php

declare(strict_types=1);

namespace RoundlyConsulting\Options\Enums;

enum OptionChangeType: string
{
    case Set = 'set';
    case Forgotten = 'forgotten';
}
