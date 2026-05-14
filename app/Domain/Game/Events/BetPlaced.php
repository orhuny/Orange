<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\Bet;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BetPlaced
{
    use Dispatchable, SerializesModels;

    public function __construct(public Bet $bet) {}
}
