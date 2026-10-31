<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Dice;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;

/** Recomputes a dice roll from a revealed player seed pair and the bet's nonce. */
final class DiceVerifier implements FairnessVerifier
{
    public const ALGORITHM = 'hmac-sha256-stream-dice';

    public function describe(Game $game): array
    {
        return [
            'algorithm' => self::ALGORITHM,
            'rtp' => DiceEngine::rtp($game),
            'house_edge' => number_format((float) $game->house_edge, 4, '.', ''),
            'inputs' => [
                'server_seed' => 'revealed when the player rotates their seed pair (its SHA-256 is shown beforehand)',
                'client_seed' => "the player's own client seed",
                'nonce' => 'the bet number on that seed pair, shown with every bet',
            ],
            'formula' => 'block_k = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}:{k}"), k = 0, 1, …; '
                .'read unsigned 32-bit big-endian integers u in order; roll = first u < 4294960000 (= floor(2^32 / 10000) * 10000), '
                .'mod 10000; shown as roll / 100 (0.00–99.99). Under T wins if roll < T * 100; over T wins if roll >= T * 100. '
                .'multiplier = floor4(rtp * 100 / chance), chance = T (under) or 100 - T (over).',
        ];
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'direction' => ['required', 'in:under,over'],
        ];
    }

    public function verify(Game $game, string $serverSeed, string $clientSeed, int $nonce, array $params): array
    {
        $roll = (new RandomStream($serverSeed, $clientSeed, $nonce))->nextInt(DiceEngine::OUTCOMES);
        $result = DiceEngine::resolve(DiceEngine::rtp($game), [
            'target' => bcadd((string) $params['target'], '0', 2),
            'direction' => (string) $params['direction'],
        ], $roll);

        return [
            'algorithm' => self::ALGORITHM,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'roll' => $result->outcome['roll'],
            'win' => $result->won,
            'payout_multiplier' => $result->outcome['payout_multiplier'],
        ];
    }
}
