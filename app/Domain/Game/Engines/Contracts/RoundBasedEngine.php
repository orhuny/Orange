<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Contracts;

use App\Domain\Game\Engines\RoundSchedule;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\OutcomeNotPublic;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use Carbon\CarbonInterface;

/** A multiplayer game played in shared rounds: waiting → betting → running → finished → settled. */
interface RoundBasedEngine extends GameEngine
{
    public function schedule(Game $game): RoundSchedule;

    /**
     * Validates the player's per-bet choices (crash: auto_cashout_at).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws InvalidBetParams
     */
    public function validateBetParams(Game $game, array $params): array;

    /**
     * Derives the round's outcome from its seeds when it starts. The returned
     * attributes are stored on the round and stay secret until it finishes.
     *
     * @return array<string, mixed>
     */
    public function resolveRound(GameRound $round): array;

    /** How long the running phase lasts once started, in seconds. */
    public function runningDuration(GameRound $round): float;

    /**
     * Live state broadcast while the round runs. Must never contain the outcome.
     *
     * @return array<string, mixed>
     */
    public function publicTick(GameRound $round, CarbonInterface $now): array;

    /**
     * The outcome, once it may be shown.
     *
     * @return array<string, mixed>
     *
     * @throws OutcomeNotPublic while the round is still live
     */
    public function publicResult(GameRound $round): array;
}
