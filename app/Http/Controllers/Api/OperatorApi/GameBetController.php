<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OperatorApi;

use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\Contracts\RoundBasedEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\StakeNotTaken;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\BetPlacementService;
use App\Domain\Game\Services\InstantPlayService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /v1/games/{code}/bets — one bet endpoint for every game. The game's engine
 * decides what the bet needs: a round (crash), nothing (instant games) or a session.
 * POST /v1/bets stays as it is for crash.
 */
class GameBetController
{
    public function __construct(
        private readonly EngineRegistry $engines,
        private readonly BetPlacementService $roundBets,
        private readonly InstantPlayService $instantBets,
    ) {}

    public function __invoke(Request $request, string $code): JsonResponse
    {
        /** @var Operator $operator */
        $operator = $request->attributes->get('operator');

        $game = Game::query()->where('code', $code)->first();
        if (! $game instanceof Game || ! $this->engines->has($game->engineType())) {
            return GameErrors::unknownGame();
        }

        $validated = $request->validate([
            'player_external_id' => ['required', 'string', 'min:1', 'max:128'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,8})?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'round_uuid' => ['nullable', 'uuid'],
            'params' => ['nullable', 'array'],
        ]);

        $engine = $this->engines->forGame($game);

        try {
            return match (true) {
                $engine instanceof RoundBasedEngine => $this->roundBet($request, $operator, $game, $engine, $validated),
                $engine instanceof InstantEngine => response()->json(InstantBetResponse::toArray($this->instantBets->play(
                    $engine,
                    $operator,
                    $game,
                    (string) $validated['player_external_id'],
                    (string) $validated['amount'],
                    strtoupper((string) $validated['currency']),
                    (string) $request->header('Idempotency-Key'),
                    (array) ($validated['params'] ?? []),
                ), $game), 201),
                default => response()->json(['error' => 'unsupported_game_type'], 422),
            };
        } catch (BettingClosed|GameNotAvailable|CurrencyNotSupported|StakeOutsideLimits|InvalidBetParams|StakeNotTaken $e) {
            return GameErrors::render($e) ?? throw $e;
        }
    }

    /** @param  array<string, mixed>  $validated */
    private function roundBet(Request $request, Operator $operator, Game $game, RoundBasedEngine $engine, array $validated): JsonResponse
    {
        $params = $engine->validateBetParams($game, (array) ($validated['params'] ?? []));

        // The given round, which must belong to this game, or the one now taking bets.
        $round = isset($validated['round_uuid'])
            ? GameRound::query()->where('game_id', $game->id)->where('round_uuid', $validated['round_uuid'])->first()
            : GameRound::query()->where('game_id', $game->id)->where('state', RoundState::Betting)->latest('id')->first();

        if (! $round instanceof GameRound) {
            return isset($validated['round_uuid'])
                ? response()->json(['error' => 'unknown_round'], 404)
                : response()->json(['error' => 'betting_closed', 'message' => 'No round of this game is taking bets.'], 409);
        }

        $autoCashoutAt = $params['auto_cashout_at'] ?? null;

        $bet = $this->roundBets->place(
            operator: $operator,
            externalPlayerId: (string) $validated['player_external_id'],
            roundUuid: $round->round_uuid,
            amount: (string) $validated['amount'],
            currency: strtoupper((string) $validated['currency']),
            idempotencyKey: (string) $request->header('Idempotency-Key'),
            autoCashoutAt: $autoCashoutAt === null ? null : (float) $autoCashoutAt,
        );

        // The same keys as POST /v1/bets, plus the game.
        return response()->json([
            'bet_id' => $bet->id,
            'game' => $game->code,
            'status' => $bet->getAttribute('status')?->value,
            'round_uuid' => $round->round_uuid,
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            'wallet_tx_id' => $bet->external_tx_id,
        ], 201);
    }
}
