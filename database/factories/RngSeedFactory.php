<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\ProvablyFair\Models\RngSeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RngSeed>
 */
class RngSeedFactory extends Factory
{
    protected $model = RngSeed::class;

    public function definition(): array
    {
        $seed = bin2hex(random_bytes(32));

        return [
            'server_seed' => $seed,
            'server_seed_hash' => hash('sha256', $seed),
            'rotated_at' => null,
            'revealed_at' => null,
            'used_round_count' => 0,
        ];
    }

    public function rotated(): static
    {
        return $this->state(['rotated_at' => now()]);
    }

    public function revealed(): static
    {
        return $this->state([
            'rotated_at' => now()->subMinute(),
            'revealed_at' => now(),
        ]);
    }
}
