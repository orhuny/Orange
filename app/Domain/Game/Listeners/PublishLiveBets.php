<?php

declare(strict_types=1);

namespace App\Domain\Game\Listeners;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Services\LiveBets;

/** Keeps the game pages' live bet list current: a bet that counts, a cash-out. */
final class PublishLiveBets
{
    public function __construct(private readonly LiveBets $live) {}

    public function onBetPlaced(BetPlaced $event): void
    {
        // A direct-play stake confirmed after its round ended counts as lost at once: that
        // round's list has moved on.
        if ($event->bet->getAttribute('status') === BetStatus::Placed) {
            $this->live->publish($event->bet);
        }
    }

    public function onCashoutCompleted(CashoutCompleted $event): void
    {
        $this->live->publish($event->cashout->bet);
    }
}
