<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

use App\Domain\ProvablyFair\Models\RngSeed;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SeedRotator
{
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

        $seed->forceFill(['revealed_at' => now()])->save();

        return $seed;
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
}
