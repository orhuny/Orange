<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Fairness;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Models\Game;
use App\Http\Controllers\Api\OperatorApi\GameErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated fairness endpoints for every game (rate limited):
 *
 * - GET /v1/fairness/{code}         what a player needs to verify outcomes
 * - GET /v1/fairness/{code}/verify  recompute an outcome from revealed seeds
 */
class GameFairnessController
{
    public function __construct(private readonly EngineRegistry $engines) {}

    public function show(string $code): JsonResponse
    {
        $game = $this->game($code);
        if ($game === null) {
            return GameErrors::unknownGame();
        }

        return response()->json([
            'game' => $game->code,
            'type' => $game->engineType(),
            'verify_url' => url("/v1/fairness/{$game->code}/verify"),
        ] + $this->engines->forGame($game)->verifier()->describe($game));
    }

    public function verify(Request $request, string $code): JsonResponse
    {
        $game = $this->game($code);
        if ($game === null) {
            return GameErrors::unknownGame();
        }

        $verifier = $this->engines->forGame($game)->verifier();
        $validated = $request->validate([
            'server_seed' => ['required', 'string', 'min:8', 'max:128'],
            'client_seed' => ['required', 'string', 'min:1', 'max:128'],
            'nonce' => ['required', 'integer', 'min:0'],
            'expected_server_seed_hash' => ['nullable', 'string', 'size:64'],
        ] + $verifier->rules());

        $result = $verifier->verify(
            $game,
            (string) $validated['server_seed'],
            (string) $validated['client_seed'],
            (int) $validated['nonce'],
            array_diff_key($validated, array_flip(['server_seed', 'client_seed', 'nonce', 'expected_server_seed_hash'])),
        );

        $expected = $validated['expected_server_seed_hash'] ?? null;

        return response()->json(['game' => $game->code] + $result + [
            'expected_hash_matches' => $expected === null ? null : hash_equals(hash('sha256', (string) $validated['server_seed']), strtolower((string) $expected)),
        ]);
    }

    private function game(string $code): ?Game
    {
        // Any published game, disabled ones included: past outcomes must stay verifiable.
        $game = Game::query()->where('code', $code)->where('status', '!=', 'draft')->first();

        return $game instanceof Game && $this->engines->has($game->engineType()) ? $game : null;
    }
}
