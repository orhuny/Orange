<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BettingOpened implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameRound $round, public ?int $bettingSeconds = null) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [
            new Channel("round.{$this->round->round_uuid}"),
            new Channel('crash'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'round.betting_opened';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round_uuid' => $this->round->round_uuid,
            'server_seed_hash' => $this->round->serverSeed?->server_seed_hash,
            'nonce' => $this->round->nonce,
            'betting_opened_at' => $this->round->betting_opened_at?->toIso8601String(),
            'betting_seconds' => $this->bettingSeconds ?? (int) config('crash.betting_seconds', 10),
        ];
    }
}
