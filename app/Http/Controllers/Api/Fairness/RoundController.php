<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Fairness;

use App\Domain\Game\Engines\Contracts\RoundBasedEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\OutcomeNotPublic;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;

/**
 * GET /v1/rounds/{uuid} — public, unauthenticated (rate limited): a round's result and
 * everything needed to check it, for the round details a game page shows. The server
 * seed and the outcome appear only once the round is over, as in its broadcasts.
 */
class RoundController
{
    public function __construct(private readonly EngineRegistry $engines) {}

    public function show(string $uuid): JsonResponse
    {
        $round = GameRound::query()->with(['game', 'serverSeed'])->where('round_uuid', $uuid)->first();
        $game = $round?->game;
        // Like the per-game fairness endpoints: any published game, disabled ones included.
        $engine = $game instanceof Game && $game->getAttribute('status') !== 'draft' && $this->engines->has($game->engineType())
            ? $this->engines->forGame($game)
            : null;
        if (! $round instanceof GameRound || ! $game instanceof Game || ! $engine instanceof RoundBasedEngine) {
            return response()->json(['error' => 'round_not_found'], 404);
        }

        try {
            $result = $engine->publicResult($round);
        } catch (OutcomeNotPublic) {
            $result = null;
        }
        $serverSeed = $round->revealedServerSeed();
        $state = $round->getAttribute('state');
        $at = fn (string $column): ?string => ($t = $round->getAttribute($column)) instanceof CarbonInterface ? $t->toIso8601String() : null;

        return response()->json([
            'round_id' => $round->id,
            'round_uuid' => $round->round_uuid,
            'game' => $game->code,
            'state' => $state instanceof RoundState ? $state->value : null,
            'betting_opened_at' => $at('betting_opened_at'),
            'started_at' => $at('started_at'),
            'crashed_at' => $at('crashed_at'),
            'settled_at' => $at('settled_at'),
            'result' => $result,
            'fairness' => [
                'server_seed_hash' => $round->serverSeed?->server_seed_hash,
                'server_seed' => $serverSeed,
                'client_seed' => $round->client_seed,
                'nonce' => $round->nonce,
                // Recomputed from the revealed seed, as GET /v1/fairness/{code}/verify does. A
                // round that never started has no outcome to check.
                'verification' => $serverSeed === null || $result === null || $at('started_at') === null
                    ? null
                    : $engine->verifier()->verify($game, $serverSeed, (string) $round->client_seed, (int) $round->nonce, []),
            ],
        ]);
    }
}
