<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Exceptions\SeedInUse;
use App\Domain\ProvablyFair\Services\PlayerSeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /v1/players/{external_id}/seeds and POST …/seeds/rotate: a player's seed pair for
 * instant games, for operators that show fairness tools in their own UI (relay play).
 */
class PlayerSeedController
{
    public function __construct(private readonly PlayerSeedService $seeds) {}

    public function show(Request $request, string $externalId): JsonResponse
    {
        $player = $this->player($request, $externalId);

        return $player === null
            ? response()->json(['error' => 'player_not_found'], 404)
            : response()->json(SeedResponse::active($this->seeds->active($player)));
    }

    public function rotate(Request $request, string $externalId): JsonResponse
    {
        $player = $this->player($request, $externalId);
        if ($player === null) {
            return response()->json(['error' => 'player_not_found'], 404);
        }

        $validated = $request->validate(['client_seed' => ['nullable', 'string', 'min:1', 'max:64']]);

        try {
            [$previous, $current] = $this->seeds->rotate($player, $validated['client_seed'] ?? null);
        } catch (SeedInUse $e) {
            return response()->json(['error' => 'bets_in_progress', 'message' => $e->getMessage()], 409);
        }

        return response()->json(SeedResponse::rotated($previous, $current));
    }

    private function player(Request $request, string $externalId): ?Player
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        return Player::query()->where('operator_id', $operator->id)->where('external_player_id', $externalId)->first();
    }
}
