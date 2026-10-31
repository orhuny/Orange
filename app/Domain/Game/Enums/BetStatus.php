<?php

declare(strict_types=1);

namespace App\Domain\Game\Enums;

enum BetStatus: string
{
    case Placed = 'placed';

    /** Direct play: created, waiting for the operator to confirm the stake debit. */
    case Pending = 'pending';
    case Won = 'won';
    case Lost = 'lost';
    case Refunded = 'refunded';
    case RolledBack = 'rolled_back';

    /** The operator declined the stake debit; nothing was taken or played. */
    case Rejected = 'rejected';
}
