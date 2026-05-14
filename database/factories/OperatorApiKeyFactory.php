<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\OperatorApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<OperatorApiKey>
 */
class OperatorApiKeyFactory extends Factory
{
    protected $model = OperatorApiKey::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'key_id' => 'orange_'.Str::lower(Str::random(24)),
            'secret_hash' => Hash::make(Str::random(64)),
            'scopes' => [],
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function withPlaintextSecret(string $secret): static
    {
        return $this->state(['secret_hash' => Hash::make($secret)]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
