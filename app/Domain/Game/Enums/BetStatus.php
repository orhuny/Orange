<?php

declare(strict_types=1);

namespace App\Domain\Game\Enums;

enum BetStatus: string
{
    case Placed = 'placed';
    case Won = 'won';
    case Lost = 'lost';
    case Refunded = 'refunded';
    case RolledBack = 'rolled_back';
}
