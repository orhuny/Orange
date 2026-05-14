<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Enums;

enum WebhookEvent: string
{
    case BetPlaced = 'bet.placed';
    case CashoutDone = 'cashout.done';
    case RoundCrashed = 'round.crashed';
    case RoundSettled = 'round.settled';
}
