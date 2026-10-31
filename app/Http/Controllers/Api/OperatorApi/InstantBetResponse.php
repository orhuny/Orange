<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;

/** The response for an instant bet: its result, and what is needed to verify it later. */
final class InstantBetResponse
{
    /** @return array<string, mixed> */
    public static function toArray(Bet $bet, Game $game): array
    {
        $seed = $bet->playerSeed;

        return [
            'bet_id' => $bet->id,
            'game' => $game->code,
            'status' => $bet->getAttribute('status')?->value,
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            'multiplier' => $bet->multiplier === null ? null : (string) $bet->multiplier,
            'payout_amount' => $bet->payout_amount === null ? null : (string) $bet->payout_amount,
            'outcome' => $bet->getAttribute('outcome'),
            'fairness' => $seed === null ? null : [
                'server_seed_hash' => $seed->server_seed_hash,
                'client_seed' => $seed->client_seed,
                'nonce' => $bet->nonce,
            ],
            'wallet_tx_id' => $bet->external_tx_id,
        ];
    }
}
