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
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\BetPlacementService;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Game\Services\InstantPlayService;
use App\Domain\Game\Services\LobbyService;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\ProvablyFair\Exceptions\SeedInUse;
use App\Domain\ProvablyFair\Services\PlayerSeedService;
use App\Domain\Wallet\Services\ReportedBalance;
use App\Domain\Wallet\Services\WalletBalances;
use App\Http\Controllers\Api\OperatorApi\GameErrors;
use App\Http\Controllers\Api\OperatorApi\InstantBetResponse;
use App\Http\Controllers\Api\OperatorApi\SeedResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The player API behind Orange's game and lobby pages (direct play). The play session
 * fixes the operator, player and currency; bets are confirmed with the operator's wallet
 * before they count.
 */
class PlayController
{
    /** How many of a player's bets the bet history shows. */
    private const BET_HISTORY_SIZE = 30;

    public function __construct(
        private readonly EngineRegistry $engines,
        private readonly BetPlacementService $roundBets,
        private readonly CashoutService $cashouts,
        private readonly InstantPlayService $instantBets,
        private readonly PlayerSeedService $seeds,
        private readonly LobbyService $lobbies,
        private readonly GameLimits $limits,
        private readonly WalletBalances $balances,
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

    /** GET /play-api/session — asked as a game (or the lobby) opens. */
    public function session(Request $request): JsonResponse
    {
        $session = $this->playSession($request);
        // An operator whose wallet answers /wallet/balance is asked now, so the game starts
        // from the wallet's own figure rather than from the last bet's (or the casino page's).
        $this->balances->ask($session->operator, $session->player, $session->currency);
        $known = $this->reportedBalance($session);

        return response()->json([
            'player' => [
                'id' => $session->player->external_player_id,
                // The operator's display name for the player, when its launch gave one.
                'username' => $session->player->username,
                'currency' => $session->currency,
            ],
            'game' => $session->game?->code,
            'lobby' => $session->lobby,
            'language' => $session->language,
            'return_url' => $session->return_url,
            'balance' => $known->balance,
            // No wallet call since this launch: the operator page's own figure is newer.
            'balance_before_launch' => $this->beforeLaunch($known, $session),
            // A win or refund of this session is still on its way to the wallet.
            'balance_pending' => $known->pending,
            // The session ends after this long without a request (null: never); the page
            // counts it down itself, so it can end a session the player walked away from.
            'idle_timeout_seconds' => $session->operator->sessionIdleSeconds(),
        ]);
    }

    /**
     * GET /play-api/balance — the player's balance as the operator's wallet last reported it.
     * The page asks after a win whose payment it can't see (a crash cash-out, paid from the
     * queue) and again while `pending`, until the wallet has answered for it.
     */
    public function balance(Request $request): JsonResponse
    {
        $session = $this->playSession($request);
        $known = $this->reportedBalance($session);

        return response()->json([
            'balance' => $known->balance,
            'currency' => $session->currency,
            'pending' => $known->pending,
            'before_launch' => $this->beforeLaunch($known, $session),
            // Which wallet call (or balance check) reported it, and when: what the figure can be
            // traced back to.
            'wallet_tx_id' => $known->txId,
            'balance_check_id' => $known->balanceCheckId,
            'reported_at' => $known->reportedAt?->toIso8601ZuluString('microsecond'),
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
        try {
            $this->limits->assertDirectPlay($game, $session->operator);
        } catch (GameNotAvailable $e) {
            return GameErrors::render($e) ?? throw $e;
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

                $known = $this->reportedBalance($session);

                return response()->json(InstantBetResponse::toArray($bet, $game) + [
                    // The wallet's own figures, never worked out here: right after the stake
                    // (shown while the outcome plays), and the newest one, which has any win
                    // paid in (shown once it has played) unless that win is still pending.
                    'balance_after_stake' => $this->balances->reportedBy($bet->external_tx_id === null ? null : (int) $bet->external_tx_id),
                    'player_balance' => $known->balance,
                    'balance_pending' => $known->pending,
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
        $known = $this->reportedBalance($session);

        return response()->json([
            'bet_id' => $bet->id,
            'game' => $game->code,
            'status' => $bet->getAttribute('status')?->value,
            'round_uuid' => $round->round_uuid,
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            'auto_cashout_at' => $bet->auto_cashout_at === null ? null : (string) $bet->auto_cashout_at,
            'player_balance' => $known->balance,
            'balance_pending' => $known->pending,
        ], 201);
    }

    /** GET /play-api/games/{code}/bets — the player's latest bets on the game, newest first. */
    public function bets(Request $request, string $code): JsonResponse
    {
        $session = $this->playSession($request);
        $game = $this->playableGame($session, $code);
        if ($game === null) {
            return GameErrors::unknownGame();
        }

        $bets = Bet::query()
            ->with(['round', 'playerSeed'])
            ->where('operator_id', $session->operator_id)
            ->where('player_id', $session->player_id)
            ->where('game_id', $game->id)
            // Only bets that counted: not stakes declined, rolled back or still being debited.
            ->whereIn('status', [BetStatus::Placed->value, BetStatus::Won->value, BetStatus::Lost->value, BetStatus::Refunded->value])
            ->latest('id')
            ->limit(self::BET_HISTORY_SIZE)
            ->get();

        return response()->json(['bets' => $bets->map(function (Bet $bet) use ($game): array {
            $status = $bet->getAttribute('status');
            $placedAt = $bet->getAttribute('placed_at');
            $round = $bet->round;
            $seed = $bet->playerSeed;

            return [
                'bet_id' => $bet->id,
                'placed_at' => $placedAt instanceof CarbonInterface ? $placedAt->toIso8601String() : null,
                'amount' => (string) $bet->amount,
                'currency' => $bet->currency,
                'status' => $status instanceof BetStatus ? $status->value : null,
                // What the player cashed out at; a lost bet has none.
                'multiplier' => $status === BetStatus::Won ? (string) $bet->multiplier : null,
                'payout_amount' => $bet->payout_amount === null ? null : (string) $bet->payout_amount,
                'round_id' => $round?->id,
                'round_uuid' => $round?->round_uuid,
                // Only once the round is over: a bet on the live round must not reveal it.
                'crash_point' => $round?->publicCrashPoint(),
                'game_type' => $game->engineType(),
                'details' => $this->historyDetails($game, $bet),
                // An instant bet's seed pair and nonce, to verify it once the seed is rotated.
                // Never the server seed itself: the hash commits to it.
                'fairness' => $seed === null ? null : [
                    'server_seed_hash' => $seed->server_seed_hash,
                    'client_seed' => $seed->client_seed,
                    'nonce' => (int) $bet->getAttribute('nonce'),
                ],
            ];
        })->all()]);
    }

    /**
     * What a game's history row shows beyond the stake and the win; null when it has nothing
     * more. A slot: its mode, the bet the stake was for, the win in multiples of that bet,
     * the free spins played and whether the max win was reached.
     *
     * @return array<string, mixed>|null
     */
    private function historyDetails(Game $game, Bet $bet): ?array
    {
        $params = (array) $bet->getAttribute('params');
        $outcome = (array) $bet->getAttribute('outcome');
        if ($game->engineType() !== 'slot' || $outcome === []) {
            return null;
        }
        $cost = (string) ($params['cost'] ?? '1');

        return [
            'mode' => (string) ($params['mode'] ?? 'base'),
            'bet' => bccomp($cost, '0', 2) > 0 ? bcdiv((string) $bet->amount, $cost, 2) : (string) $bet->amount,
            'win_x' => isset($outcome['total_win_x']) ? (string) $outcome['total_win_x'] : null,
            'free_spins' => (int) ($outcome['free_spins'] ?? 0),
            'max_win' => (bool) ($outcome['max_win_reached'] ?? false),
        ];
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
            // Most often its auto cash-out got there first: say at what, as the operator API
            // does, so the page shows the win instead of a failed cash-out.
            $existing = $status === BetStatus::Won ? Cashout::query()->where('bet_id', $bet->id)->first() : null;

            return response()->json([
                'error' => $status === BetStatus::Lost ? 'round_crashed' : 'already_settled',
                'bet_status' => $status?->value,
                'cashout' => $existing instanceof Cashout ? [
                    'multiplier' => (float) $existing->multiplier,
                    'payout_amount' => (string) $existing->payout_amount,
                    'is_auto' => $existing->is_auto,
                ] : null,
            ], 409);
        }

        // The win is paid from the queue: usually still pending here, so the page asks
        // GET /balance until the wallet has answered for it.
        $known = $this->reportedBalance($session);

        return response()->json([
            'bet_id' => $bet->id,
            'multiplier' => (float) $cashout->multiplier,
            'payout_amount' => (string) $cashout->payout_amount,
            'is_auto' => $cashout->is_auto,
            'player_balance' => $known->balance,
            'balance_pending' => $known->pending,
        ]);
    }

    /** GET /play-api/games/{code} — what the game's rules show the player: its RTP and the limits their bets are played with. */
    public function game(Request $request, string $code): JsonResponse
    {
        $session = $this->playSession($request);
        $game = $this->playableGame($session, $code);
        if ($game === null) {
            return GameErrors::unknownGame();
        }

        try {
            $limits = $this->limits->for($game, $session->operator, $session->currency);
        } catch (GameNotAvailable|CurrencyNotSupported $e) {
            return GameErrors::render($e) ?? throw $e;
        }

        return response()->json([
            'code' => $game->code,
            'name' => $game->name,
            'rtp' => number_format((float) $game->rtp_target, 4, '.', ''),
            'currency' => $limits->currency,
            // Decimal strings; null: no limit.
            'limits' => [
                'min_bet' => $limits->minBet,
                'max_bet' => $limits->maxBet,
                'max_win' => $limits->maxWin,
            ],
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

    /** The balance the operator's wallet last reported for this player (WalletBalances). */
    private function reportedBalance(PlaySession $session): ReportedBalance
    {
        $launchedAt = $session->getAttribute('created_at');

        return $this->balances->latest(
            (string) $session->operator_id,
            (string) $session->player_id,
            $launchedAt instanceof CarbonInterface ? $launchedAt : null,
        );
    }

    /**
     * Whether the balance was reported before this session was launched. Orange learns the
     * balance only from wallet calls, so a deposit at the casino since then isn't in it: the
     * host page then shows the balance the operator's page reports instead (play-host.js).
     */
    private function beforeLaunch(ReportedBalance $known, PlaySession $session): bool
    {
        $launchedAt = $session->getAttribute('created_at');

        return $known->reportedAt === null || ! $launchedAt instanceof CarbonInterface || $known->reportedAt->lt($launchedAt);
    }
}
