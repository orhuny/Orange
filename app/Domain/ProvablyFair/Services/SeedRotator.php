<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use App\Domain\ProvablyFair\Models\RngSeed;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SeedRotator
{
    /** Round states whose outcome is still secret; a seed they use must never be revealed. */
    private const LIVE_ROUND_STATES = [RoundState::Waiting, RoundState::Betting, RoundState::Running];

    public function __construct(private readonly ProvablyFairService $math) {}

    public function current(): RngSeed
    {
        return RngSeed::active()->latest('id')->first() ?? $this->mintNew();
    }

    public function mintNew(): RngSeed
    {
        $seed = bin2hex(random_bytes(32));

        return RngSeed::create([
            'server_seed' => $seed,
            'server_seed_hash' => $this->math->hashSeed($seed),
        ]);
    }

    /**
     * A fresh seed used by exactly one round. Each round's seed is revealed when the
     * round settles, so it must not determine any other round's outcome.
     */
    public function mintForRound(): RngSeed
    {
        $seed = $this->mintNew();
        $seed->forceFill(['used_round_count' => 1])->save();

        return $seed;
    }

    public function rotate(): RngSeed
    {
        return DB::transaction(function (): RngSeed {
            RngSeed::active()->update(['rotated_at' => now()]);

            return $this->mintNew();
        });
    }

    public function reveal(RngSeed $seed): RngSeed
    {
        if ($seed->revealed_at !== null) {
            return $seed;
        }

        if ($seed->isActive()) {
            throw new RuntimeException('Cannot reveal an active (un-rotated) seed.');
        }

        if ($this->isUsedByLiveRound($seed)) {
            throw new RuntimeException('Cannot reveal a seed that a live round still depends on.');
        }

        $seed->forceFill(['revealed_at' => now()])->save();

        return $seed;
    }

    /**
     * Takes a seed out of use once its round has finished: always rotated, and
     * revealed unless another live round still uses it (only possible for seeds
     * shared across rounds before seeds became per-round).
     */
    public function retire(RngSeed $seed): RngSeed
    {
        if ($seed->rotated_at === null) {
            $seed->forceFill(['rotated_at' => now()])->save();
        }

        if ($seed->revealed_at === null && ! $this->isUsedByLiveRound($seed)) {
            $seed->forceFill(['revealed_at' => now()])->save();
        }

        return $seed;
    }

    /**
     * Retires every still-active seed whose rounds have all finished, e.g. after
     * rounds were force-settled as orphans or the seed was shared across rounds.
     * Returns the number of seeds retired.
     */
    public function retireFinished(): int
    {
        $seeds = RngSeed::active()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('game_rounds')
                ->whereColumn('game_rounds.server_seed_id', 'rng_seeds.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('game_rounds')
                ->whereColumn('game_rounds.server_seed_id', 'rng_seeds.id')
                ->whereIn('game_rounds.state', array_map(fn (RoundState $s) => $s->value, self::LIVE_ROUND_STATES)))
            ->get();

        foreach ($seeds as $seed) {
            $this->retire($seed);
        }

        return $seeds->count();
    }

    public function trackRoundUsage(RngSeed $seed): RngSeed
    {
        $seed->increment('used_round_count');

        $threshold = (int) config('provably_fair.seed_rotation_rounds', 1000);

        if ($threshold > 0 && $seed->used_round_count >= $threshold && $seed->isActive()) {
            return $this->rotate();
        }

        return $seed->refresh();
    }

    private function isUsedByLiveRound(RngSeed $seed): bool
    {
        return GameRound::query()
            ->where('server_seed_id', $seed->id)
            ->whereIn('state', self::LIVE_ROUND_STATES)
            ->exists();
    }
}
