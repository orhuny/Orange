<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlayApi;

use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\Contracts\RoundBasedEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Exceptions\StakeNotTaken;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\BetPlacementService;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Game\Services\InstantPlayService;
use App\Domain\Game\Services\LobbyService;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\ProvablyFair\Exceptions\SeedInUse;
use App\Domain\ProvablyFair\Services\PlayerSeedService;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Models\WalletTransaction;
use App\Http\Controllers\Api\OperatorApi\GameErrors;
use App\Http\Controllers\Api\OperatorApi\InstantBetResponse;
use App\Http\Controllers\Api\OperatorApi\SeedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The player API behind Orange's game and lobby pages (direct play). The play session
 * fixes the operator, player and currency; bets are confirmed with the operator's wallet
 * before they count.
 */
class PlayController
{
    public function __construct(
        private readonly EngineRegistry $engines,
        private readonly BetPlacementService $roundBets,
        private readonly CashoutService $cashouts,
        private readonly InstantPlayService $instantBets,
        private readonly PlayerSeedService $seeds,
        private readonly LobbyService $lobbies,
    ) {}

    /** GET /play-api/lobby — the lobby's tiles, categories, recent and popular games, and theme. */
    public function lobby(Request $request): JsonResponse
    {
        $session = $this->playSession($request);
        if (! $session->lobby) {
            return response()->json(['error' => 'not_a_lobby_session'], 403);
        }

        return response()->json($this->lobbies->forSession($session) + ['theme' => $session->operator->lobbyTheme()]);
    }

    /** GET /play-api/session */
    public function session(Request $request): JsonResponse
    {
        $session = $this->playSession($request);

        return response()->json([
            'player' => ['id' => $session->player->external_player_id, 'currency' => $session->currency],
            'game' => $session->game?->code,
            'lobby' => $session->lobby,
            'language' => $session->language,
            'return_url' => $session->return_url,
            'balance' => $this->lastKnownBalance($session),
        ]);
    }

    /** POST /play-api/games/{code}/bets */
    public function bet(Request $request, string $code): JsonResponse
    {
        $session = $this->playSession($request);
        $game = $this->playableGame($session, $code);
        if ($game === null) {
            return GameErrors::unknownGame();
        }

        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            return response()->json(['error' => 'missing_idempotency_key'], 400);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,8})?$/'],
            'round_uuid' => ['nullable', 'uuid'],
            'params' => ['nullable', 'array'],
        ]);

        $engine = $this->engines->forGame($game);

        try {
            if ($engine instanceof InstantEngine) {
                $bet = $this->instantBets->play(
                    $engine,
                    $session->operator,
                    $game,
                    $session->player->external_player_id,
                    (string) $validated['amount'],
                    $session->currency,
                    "ps-{$key}",
                    (array) ($validated['params'] ?? []),
                );

                return response()->json(InstantBetResponse::toArray($bet, $game) + [
                    'player_balance' => $this->lastKnownBalance($session),
                ], 201);
            }

            if (! $engine instanceof RoundBasedEngine) {
                return response()->json(['error' => 'unsupported_game_type'], 422);
            }

            $params = $engine->validateBetParams($game, (array) ($validated['params'] ?? []));
            $round = $this->bettingRound($game, $validated['round_uuid'] ?? null);
            $autoCashoutAt = $params['auto_cashout_at'] ?? null;

            $bet = $this->roundBets->place(
                operator: $session->operator,
                externalPlayerId: $session->player->external_player_id,
                roundUuid: $round->round_uuid,
                amount: (string) $validated['amount'],
                currency: $session->currency,
                // Namespaced so a browser key can never collide with an operator's own keys.
                idempotencyKey: "ps-{$key}",
                autoCashoutAt: $autoCashoutAt === null ? null : (float) $autoCashoutAt,
                confirmStake: true,
            );
        } catch (BettingClosed|GameNotAvailable|CurrencyNotSupported|StakeOutsideLimits|InvalidBetParams|StakeNotTaken $e) {
            return GameErrors::render($e) ?? throw $e;
        }

        return response()->json([
            'bet_id' => $bet->id,
            'game' => $game->code,
            'status' => $bet->getAttribute('status')?->value,
            'round_uuid' => $round->round_uuid,
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            'auto_cashout_at' => $bet->auto_cashout_at === null ? null : (string) $bet->auto_cashout_at,
            'player_balance' => $this->lastKnownBalance($session),
        ], 201);
    }

    /** POST /play-api/bets/{bet}/cashout */
    public function cashout(Request $request, int $betId): JsonResponse
    {
        $session = $this->playSession($request);
        $bet = Bet::query()
            ->whereKey($betId)
            ->where('operator_id', $session->operator_id)
            ->where('player_id', $session->player_id)
            ->first();

        if (! $bet instanceof Bet) {
            return response()->json(['error' => 'bet_not_found'], 404);
        }

        try {
            $cashout = $this->cashouts->cashout($bet);
        } catch (AlreadyCashedOut|RoundNotRunning) {
            $status = $bet->fresh()?->getAttribute('status');

            return response()->json([
                'error' => $status === BetStatus::Lost ? 'round_crashed' : 'already_settled',
                'bet_status' => $status?->value,
            ], 409);
        }

        return response()->json([
            'bet_id' => $bet->id,
            'multiplier' => (float) $cashout->multiplier,
            'payout_amount' => (string) $cashout->payout_amount,
            'is_auto' => $cashout->is_auto,
        ]);
    }

    /** GET /play-api/seeds — the player's active seed pair (server seed hashed). */
    public function seeds(Request $request): JsonResponse
    {
        return response()->json(SeedResponse::active($this->seeds->active($this->playSession($request)->player)));
    }

    /** POST /play-api/seeds/rotate — reveal the active pair and start a new one. */
    public function rotateSeeds(Request $request): JsonResponse
    {
        $validated = $request->validate(['client_seed' => ['nullable', 'string', 'min:1', 'max:64']]);

        try {
            [$previous, $current] = $this->seeds->rotate($this->playSession($request)->player, $validated['client_seed'] ?? null);
        } catch (SeedInUse $e) {
            return response()->json(['error' => 'bets_in_progress', 'message' => $e->getMessage()], 409);
        }

        return response()->json(SeedResponse::rotated($previous, $current));
    }

    private function playSession(Request $request): PlaySession
    {
        /** @var PlaySession $session */
        $session = $request->attributes->get('play_session');

        return $session;
    }

    /** The game a session may bet on: the one it was launched for, or any from the lobby. */
    private function playableGame(PlaySession $session, string $code): ?Game
    {
        $game = Game::query()->where('code', $code)->first();
        if (! $game instanceof Game || ! $this->engines->has($game->engineType())) {
            return null;
        }

        return $session->lobby || $session->game_id === $game->id ? $game : null;
    }

    /** @throws BettingClosed */
    private function bettingRound(Game $game, ?string $roundUuid): GameRound
    {
        $round = GameRound::query()
            ->where('game_id', $game->id)
            ->when($roundUuid !== null, fn ($q) => $q->where('round_uuid', $roundUuid))
            ->when($roundUuid === null, fn ($q) => $q->where('state', RoundState::Betting))
            ->latest('id')
            ->first();

        return $round instanceof GameRound ? $round : throw BettingClosed::for(RoundState::Running);
    }

    /** The balance the operator last reported for this player, if any. */
    private function lastKnownBalance(PlaySession $session): ?string
    {
        $tx = WalletTransaction::query()
            ->where('operator_id', $session->operator_id)
            ->where('player_id', $session->player_id)
            ->where('operator_callback_status', WalletCallbackStatus::Completed->value)
            ->latest('id')
            ->first();
        $balance = data_get($tx?->getAttribute('operator_response'), 'body.balance_after');

        return is_scalar($balance) ? (string) $balance : null;
    }
}
