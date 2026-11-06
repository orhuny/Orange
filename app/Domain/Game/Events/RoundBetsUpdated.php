<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Bets of a round that were placed or cashed out: rows of its live bet list (LiveBets::row). */
class RoundBetsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  list<array<string, mixed>>  $bets */
    public function __construct(public GameRound $round, public array $bets) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return RoundChannels::for($this->round);
    }

    public function broadcastAs(): string
    {
        return 'round.bets';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round_uuid' => $this->round->round_uuid,
            'bets' => $this->bets,
        ];
    }
}
