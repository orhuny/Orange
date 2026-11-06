<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Crash;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Rules\ValidHouseEdge;
use App\Domain\ProvablyFair\Services\ProvablyFairService;

/** Same maths as POST /v1/fairness/verify: bustabit HMAC with a 1-in-N house edge. */
final class CrashVerifier implements FairnessVerifier
{
    public function __construct(private readonly ProvablyFairService $math) {}

    public function describe(Game $game): array
    {
        return [
            'algorithm' => 'bustabit-style-hmac-sha256-1overN-edge',
            'house_edge' => number_format((float) $game->house_edge, 4, '.', ''),
            'house_edge_divisor' => $this->math->divisorForGame($game),
            // Public on purpose: every round has its own server seed, revealed only once the
            // round settles, so knowing the client seed predicts nothing.
            'house_client_seed' => (string) config('provably_fair.house_client_seed'),
            'inputs' => [
                'server_seed' => 'revealed in round.settled (its SHA-256 is published in round.betting_opened)',
                'client_seed' => 'house_client_seed',
                'nonce' => 'from round.betting_opened',
            ],
            'formula' => 'h = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}"); '
                .'if int(h[0..8], 16) mod divisor == 0 then 1.00 '
                .'else floor((100 * 2^52 - x) / (2^52 - x)) / 100 with x = int(h[0..13], 16)',
        ];
    }

    public function rules(): array
    {
        return [
            // The house edge the round was played with, if it has changed since.
            'house_edge' => ['nullable', 'numeric', new ValidHouseEdge],
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
            // The steps in between, as a player would redo them by hand: the first 13 hex
            // digits and their value, and whether the first 8 hit the 1-in-N edge.
            'hex' => substr($hmac, 0, 13),
            'decimal' => (int) hexdec(substr($hmac, 0, 13)),
            'instant_crash' => (int) hexdec(substr($hmac, 0, 8)) % $divisor === 0,
            'crash_point' => $this->math->crashFromHmac($hmac, $divisor),
        ];
    }
}
