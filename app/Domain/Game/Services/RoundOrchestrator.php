<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\InvalidRoundTransition;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use App\Domain\ProvablyFair\Services\SeedRotator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RoundOrchestrator
{
    public function __construct(
        private readonly SeedRotator $seeds,
        private readonly ProvablyFairService $math,
    ) {}

    public function create(Game $game, ?string $clientSeed = null): GameRound
    {
        return DB::transaction(function () use ($game, $clientSeed): GameRound {
            $seed = RngSeed::query()
                ->whereKey($this->seeds->current()->id)
                ->lockForUpdate()
                ->first();

            $nonce = $seed->used_round_count + 1;
            $seed->forceFill(['used_round_count' => $nonce])->save();

            return GameRound::create([
                'game_id' => $game->id,
                'round_uuid' => (string) Str::uuid(),
                'state' => RoundState::Waiting,
                'server_seed_id' => $seed->id,
                'client_seed' => $clientSeed ?? config('provably_fair.house_client_seed'),
                'nonce' => $nonce,
            ]);
        });
    }

    public function openBetting(GameRound $round): GameRound
    {
        return $this->transition($round, RoundState::Betting, [
            'betting_opened_at' => now(),
        ]);
    }

    public function start(GameRound $round): GameRound
    {
        $crashPoint = $this->math->crashPoint(
            $round->serverSeed->server_seed,
            $round->client_seed,
            $round->nonce,
        );

        return $this->transition($round, RoundState::Running, [
            'started_at' => now(),
            'crash_point' => $crashPoint,
        ]);
    }

    public function crash(GameRound $round): GameRound
    {
        return $this->transition($round, RoundState::Crashed, [
            'crashed_at' => now(),
        ]);
    }

    public function settle(GameRound $round): GameRound
    {
        $settled = $this->transition($round, RoundState::Settled, [
            'settled_at' => now(),
        ]);

        $seed = $settled->serverSeed()->first();
        if ($seed instanceof RngSeed) {
            $threshold = (int) config('provably_fair.seed_rotation_rounds', 1000);
            if ($threshold > 0 && $seed->used_round_count >= $threshold && $seed->isActive()) {
                $this->seeds->rotate();
            }
        }

        return $settled;
    }

    private function transition(GameRound $round, RoundState $target, array $columns): GameRound
    {
        return DB::transaction(function () use ($round, $target, $columns): GameRound {
            $fresh = GameRound::query()
                ->whereKey($round->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh->state->canTransitionTo($target)) {
                throw InvalidRoundTransition::from($fresh->state, $target);
            }

            $fresh->forceFill(['state' => $target] + $columns)->save();

            return $fresh;
        });
    }
}
