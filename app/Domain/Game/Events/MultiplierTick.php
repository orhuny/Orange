<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MultiplierTick implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GameRound $round,
        public float $multiplier,
        public float $elapsedSeconds,
    ) {}

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
        return 'round.tick';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'round_uuid' => $this->round->round_uuid,
            'multiplier' => $this->multiplier,
            'elapsed_seconds' => round($this->elapsedSeconds, 3),
        ];
    }
}
