<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\ProvablyFair\Models\PlayerSeed;

/** Seed pairs as players see them: the server seed only once revealed. */
final class SeedResponse
{
    /** @return array<string, mixed> */
    public static function active(PlayerSeed $seed): array
    {
        return [
            'server_seed_hash' => $seed->server_seed_hash,
            'client_seed' => $seed->client_seed,
            'nonce' => $seed->nonce,
            // The server seed the next pair will use, committed before its client seed is chosen.
            'next_server_seed_hash' => $seed->next_server_seed_hash,
        ];
    }

    /** @return array<string, mixed> */
    public static function rotated(PlayerSeed $previous, PlayerSeed $current): array
    {
        return [
            'previous' => [
                'server_seed' => $previous->isRevealed() ? $previous->server_seed : null,
                'server_seed_hash' => $previous->server_seed_hash,
                'client_seed' => $previous->client_seed,
                'last_nonce' => $previous->nonce,
            ],
            'current' => self::active($current),
        ];
    }
}
