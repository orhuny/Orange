<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoundSettled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public GameRound $round) {}

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
        return 'round.settled';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round_uuid' => $this->round->round_uuid,
            'settled_at' => $this->round->settled_at?->toIso8601String(),
            'server_seed' => $this->round->serverSeed?->server_seed,
            'crash_point' => (float) $this->round->crash_point,
            'next_round_in_seconds' => (int) config('crash.waiting_seconds', 5),
        ];
    }
}
