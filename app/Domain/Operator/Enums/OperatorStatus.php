<?php

declare(strict_types=1);

namespace App\Domain\Operator\Enums;

enum OperatorStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';

    public function canCallApi(): bool
    {
        return $this === self::Active;
    }
}
