<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Console\Command;

class RunCrashLoopCommand extends Command
{
    protected $signature = 'crash:loop
        {--game=crash : Game code to run}
        {--rounds=0 : Stop after N rounds (0 = forever)}
        {--betting= : Override betting window (seconds)}
        {--waiting= : Override post-crash pause (seconds)}';

    protected $description = 'Drive a continuous crash round loop with realistic timing so the /crash demo page can be observed.';

    public function handle(RoundOrchestrator $orchestrator, MultiplierCalculator $calculator): int
    {
        $game = Game::query()->where('code', $this->option('game'))->first();
        if (! $game) {
            $this->error("game '{$this->option('game')}' not found — did you run db:seed?");

            return self::FAILURE;
        }

        $bettingSeconds = (int) ($this->option('betting') ?? config('crash.betting_seconds', 10));
        $waitingSeconds = (int) ($this->option('waiting') ?? config('crash.waiting_seconds', 5));
        $maxRounds = (int) $this->option('rounds');
        $i = 0;

        $this->info("crash:loop on game '{$game->code}' — betting={$bettingSeconds}s waiting={$waitingSeconds}s");

        $orphans = GameRound::query()
            ->whereIn('state', [RoundState::Waiting, RoundState::Betting, RoundState::Running, RoundState::Crashed])
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['state' => RoundState::Settled, 'settled_at' => now(), 'updated_at' => now()]);

        if ($orphans > 0) {
            $this->warn("cleaned up {$orphans} orphan round(s) left over from a previous run");
        }

        while ($maxRounds === 0 || $i < $maxRounds) {
            $i++;
            $round = $orchestrator->create($game);
            $this->line("[{$i}] round {$round->round_uuid} created");

            $orchestrator->openBetting($round, $bettingSeconds);
            $this->line("    → betting open for {$bettingSeconds}s");
            sleep($bettingSeconds);

            $round = $orchestrator->start($round->refresh());
            $this->line("    → started, crash_point={$round->crash_point}x");

            $duration = $calculator->durationToReach((float) $round->crash_point);
            $this->line('    → expected duration '.round($duration, 2).'s');
            usleep((int) ($duration * 1_000_000));

            $orchestrator->crash($round->refresh());
            $this->line("    → CRASHED @ {$round->crash_point}x");
            sleep(1);

            $orchestrator->settle($round->refresh());
            $this->line("    → settled. cooldown {$waitingSeconds}s");
            sleep($waitingSeconds);
        }

        return self::SUCCESS;
    }
}
