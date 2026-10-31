<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Models\Game;
use App\Domain\Launch\Services\IssuedLaunch;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /v1/games/{code}/launch and POST /v1/lobby/launch: a short-lived, single-use
 * token and the URL to open (in the operator's iframe) for one player.
 */
class LaunchController
{
    public function __construct(private readonly LaunchService $launches) {}

    public function game(Request $request, string $code): JsonResponse
    {
        $game = Game::query()->where('code', $code)->first();
        if (! $game instanceof Game) {
            return GameErrors::unknownGame();
        }

        return $this->launch($request, $game, 'play_url');
    }

    public function lobby(Request $request): JsonResponse
    {
        return $this->launch($request, null, 'lobby_url');
    }

    private function launch(Request $request, ?Game $game, string $urlKey): JsonResponse
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        $validated = $request->validate([
            'player_external_id' => ['required', 'string', 'min:1', 'max:128'],
            'currency' => ['required', 'string', 'size:3'],
            'language' => ['nullable', 'string', 'max:16', 'regex:/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})?$/'],
            'return_url' => ['nullable', 'url:https,http', 'max:2048'],
        ]);

        try {
            $launch = $this->launches->issue(
                $operator,
                $game,
                $validated['player_external_id'],
                $validated['currency'],
                $validated['language'] ?? null,
                $validated['return_url'] ?? null,
            );
        } catch (GameNotAvailable|CurrencyNotSupported $e) {
            return GameErrors::render($e) ?? throw $e;
        }

        return response()->json($this->serialize($launch, $urlKey), 201);
    }

    /** @return array<string, string> */
    private function serialize(IssuedLaunch $launch, string $urlKey): array
    {
        return [
            'launch_token' => $launch->token,
            $urlKey => $launch->url,
            'expires_at' => $launch->expiresAt->toIso8601String(),
        ];
    }
}
