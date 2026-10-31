<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Crash;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\ProvablyFairService;

/** Same maths as POST /v1/fairness/verify: bustabit HMAC with a 1-in-N house edge. */
final class CrashVerifier implements FairnessVerifier
{
    public function __construct(private readonly ProvablyFairService $math) {}

    public function rules(): array
    {
        return [
            // The house edge the round was played with, if it has changed since. Bounded so
            // the derived 1-in-N divisor is a valid integer of at least 2 (0.6 → 2, 0.000001 → 10⁶).
            'house_edge' => ['nullable', 'numeric', 'gte:0.000001', 'lte:0.6'],
        ];
    }

    public function verify(Game $game, string $serverSeed, string $clientSeed, int $nonce, array $params): array
    {
        $divisor = isset($params['house_edge'])
            ? (int) round(1.0 / (float) $params['house_edge'])
            : $this->math->divisorForGame($game);
        $hmac = $this->math->hmac($serverSeed, $clientSeed, $nonce);

        return [
            'algorithm' => 'bustabit-style-hmac-sha256-1overN-edge',
            'house_edge_divisor' => $divisor,
            'server_seed_hash' => $this->math->hashSeed($serverSeed),
            'hmac' => $hmac,
            'crash_point' => $this->math->crashFromHmac($hmac, $divisor),
        ];
    }
}
