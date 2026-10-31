<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WebhookEvent: string
{
    case BetPlaced = 'bet.placed';
    case CashoutDone = 'cashout.done';
    case RoundCrashed = 'round.crashed';
    case RoundSettled = 'round.settled';

    /** A bet on a game other than crash was resolved (crash keeps its own events). */
    case BetSettled = 'bet.settled';
}
