<?php

declare(strict_types=1);

namespace App\Domain\Game\Jobs;

use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCrashRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game, public ?string $clientSeed = null) {}

    public function handle(RoundOrchestrator $orchestrator): GameRound
    {
        $round = $orchestrator->create($this->game, $this->clientSeed);
        $round = $orchestrator->openBetting($round);
        $round = $orchestrator->start($round);
        $round = $orchestrator->crash($round);

        return $orchestrator->settle($round);
    }
}
