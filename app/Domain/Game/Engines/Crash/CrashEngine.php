<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Crash;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Engines\Contracts\RoundBasedEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Engines\RoundSchedule;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\OutcomeNotPublic;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * The multiplayer crash game ("DeepDive") as an engine: an adapter over the existing
 * crash maths (ProvablyFairService, MultiplierCalculator), so behaviour is unchanged.
 */
final class CrashEngine implements RoundBasedEngine
{
    public function __construct(
        private readonly ProvablyFairService $math,
        private readonly MultiplierCalculator $calculator,
        private readonly CrashVerifier $verifier,
    ) {}

    public static function type(): string
    {
        return 'crash';
    }

    public function family(): EngineFamily
    {
        return EngineFamily::RoundBased;
    }

    /**
     * Crash has no engine settings of its own yet (growth rate and timings come from
     * config/crash.php), so existing free-form keys are kept as they are.
     */
    public function validateConfig(Game $game, array $config): array
    {
        return $config;
    }

    /** Crash points are uncapped; max win caps the payout instead. */
    public function maxMultiplier(Game $game): string
    {
        return '0';
    }

    public function verifier(): FairnessVerifier
    {
        return $this->verifier;
    }

    public function schedule(Game $game): RoundSchedule
    {
        return new RoundSchedule(
            waitingSeconds: (int) config('crash.waiting_seconds', 5),
            bettingSeconds: (int) config('crash.betting_seconds', 10),
            tickIntervalMs: (int) config('crash.tick_interval_ms', 100),
        );
    }

    public function validateBetParams(Game $game, array $params): array
    {
        $validator = Validator::make($params, [
            'auto_cashout_at' => ['nullable', 'numeric', 'gte:1.01'],
        ]);

        if ($validator->fails()) {
            throw new InvalidBetParams($validator->errors()->first());
        }

        return ['auto_cashout_at' => $validator->validated()['auto_cashout_at'] ?? null];
    }

    public function resolveRound(GameRound $round): array
    {
        // The 1-in-N house edge divisor is derived from the round's game configuration
        // so the maths tracks the rtp_target / house_edge shown in the admin panel.
        return [
            'crash_point' => $this->math->crashPoint(
                $round->serverSeed->server_seed,
                $round->client_seed,
                $round->nonce,
                $this->math->divisorForGame($round->game),
            ),
        ];
    }

    public function runningDuration(GameRound $round): float
    {
        return $this->calculator->durationToReach((float) $round->crash_point);
    }

    public function publicTick(GameRound $round, CarbonInterface $now): array
    {
        $now = Carbon::instance($now);
        $startedAt = $round->getAttribute('started_at');
        $elapsedSeconds = $startedAt instanceof CarbonInterface
            ? abs($startedAt->diffInMicroseconds($now)) / 1_000_000
            : 0.0;

        return [
            'round_uuid' => $round->round_uuid,
            'multiplier' => $this->calculator->forRound($round, $now),
            'elapsed_seconds' => round($elapsedSeconds, 3),
        ];
    }

    public function publicResult(GameRound $round): array
    {
        if (! in_array($round->getAttribute('state'), GameRound::CRASH_POINT_PUBLIC_STATES, true)) {
            throw OutcomeNotPublic::forRound($round);
        }

        // Null for a round that was force-settled before it ever started.
        return ['crash_point' => $round->publicCrashPoint()];
    }
}
