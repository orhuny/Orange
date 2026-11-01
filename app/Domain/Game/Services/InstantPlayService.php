<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\BetSettled;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\StakeNotTaken;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Domain\ProvablyFair\Services\PlayerSeedService;
use App\Domain\ProvablyFair\Services\RandomStream;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Models\WalletTransaction;
use App\Domain\Wallet\Services\DebitResult;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Plays one bet on an instant game (dice), in this order:
 *
 *   1. validate the params, the game's availability and the limits;
 *   2. take the player's next nonce and create the bet, pending;
 *   3. debit the stake and wait for the operator (relay operators that took the stake
 *      themselves answer the same tx_id idempotently);
 *   4. only then compute the outcome, write it once, and credit any win (capped at the
 *      bet's max win).
 *
 * A stake that isn't confirmed never gets an outcome: refused → rejected, unanswered →
 * rolled back.
 */
final class InstantPlayService
{
    public function __construct(
        private readonly GameLimits $limits,
        private readonly PlayerSeedService $seeds,
        private readonly WalletService $wallet,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidBetParams|GameNotAvailable|CurrencyNotSupported|StakeOutsideLimits|StakeNotTaken
     */
    public function play(
        InstantEngine $engine,
        Operator $operator,
        Game $game,
        string $externalPlayerId,
        string $amount,
        string $currency,
        string $idempotencyKey,
        array $params,
    ): Bet {
        $existing = Bet::query()->where('operator_id', $operator->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof Bet) {
            return $existing;
        }

        $params = $engine->validateParams($game, $params);
        $limits = $this->limits->for($game, $operator, $currency);
        $this->limits->assertStake($limits, $amount);

        $player = Player::query()->firstOrCreate(
            ['operator_id' => $operator->id, 'external_player_id' => $externalPlayerId],
            ['currency' => $currency, 'status' => 'active'],
        );

        $bet = DB::transaction(function () use ($operator, $game, $player, $amount, $currency, $idempotencyKey, $params, $limits): Bet {
            [$seed, $nonce] = $this->seeds->nextNonce($player);

            return Bet::create([
                'game_id' => $game->id,
                'player_seed_id' => $seed->id,
                'nonce' => $nonce,
                'player_id' => $player->id,
                'operator_id' => $operator->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => BetStatus::Pending,
                'params' => $params,
                'max_payout' => $limits->maxWin,
                'placed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        $debit = $this->wallet->debitNow($operator, $player, $amount, $currency, "bet:{$idempotencyKey}", (int) $bet->id);

        if (! $debit->accepted()) {
            $refused = $debit->outcome === DebitResult::REJECTED;
            $this->abandon($bet, $refused);

            throw $refused ? StakeNotTaken::refused($debit->error) : StakeNotTaken::unanswered();
        }

        return $this->settle($engine, $bet, $game, (string) $debit->transaction->id);
    }

    /**
     * Instant bets left pending by a process that died mid-bet (older than the grace
     * period): settled if their stake debit was confirmed (the outcome is deterministic,
     * so settling late is still fair), rolled back otherwise. Returns how many were handled.
     */
    public function settleStale(EngineRegistry $engines, int $olderThanSeconds = 120): int
    {
        $handled = 0;
        $bets = Bet::query()
            ->whereNull('round_id')
            ->where('status', BetStatus::Pending->value)
            ->where('placed_at', '<=', now()->subSeconds($olderThanSeconds))
            ->with(['game', 'operator', 'player'])
            ->lazyById();

        foreach ($bets as $bet) {
            $debit = WalletTransaction::query()
                ->where('operator_id', $bet->operator_id)
                ->where('idempotency_key', "bet:{$bet->idempotency_key}")
                ->first();
            $engine = $bet->game === null ? null : $engines->forGame($bet->game);

            if ($debit?->getAttribute('operator_callback_status') === WalletCallbackStatus::Completed && $engine instanceof InstantEngine) {
                $this->settle($engine, $bet, $bet->game, (string) $debit->id);
            } else {
                $this->abandon($bet, refused: false);
            }
            $handled++;
        }

        return $handled;
    }

    /** The stake is confirmed: now, and only now, the outcome exists. */
    private function settle(InstantEngine $engine, Bet $bet, Game $game, string $debitTxId): Bet
    {
        /** @var PlayerSeed $seed */
        $seed = PlayerSeed::query()->findOrFail($bet->player_seed_id);
        $params = (array) $bet->getAttribute('params');
        $result = $engine->play($game, $params, new RandomStream($seed->server_seed, $seed->client_seed, (int) $bet->nonce));

        $payout = '0.00000000';
        if ($result->won) {
            $payout = bcmul((string) $bet->amount, $result->multiplier, 8);
            if ($bet->max_payout !== null && bccomp($payout, (string) $bet->max_payout, 8) > 0) {
                $payout = bcadd((string) $bet->max_payout, '0', 8);
            }
        }

        $operator = $bet->operator;
        $player = $bet->player;

        // Written once (guarded on the pending status, so a concurrent settle can't run twice)
        // and in one transaction with its credit: a win is never recorded without its payment.
        $settled = DB::transaction(function () use ($bet, $result, $payout, $debitTxId, $operator, $player): bool {
            $settled = DB::table('bets')->where('id', $bet->id)->where('status', BetStatus::Pending->value)->update([
                'status' => ($result->won ? BetStatus::Won : BetStatus::Lost)->value,
                'outcome' => json_encode($result->outcome),
                'multiplier' => $result->multiplier,
                'payout_amount' => $payout,
                'settled_at' => now(),
                'external_tx_id' => $debitTxId,
                'updated_at' => now(),
            ]);

            if ($settled > 0 && bccomp($payout, '0', 8) > 0) {
                $this->wallet->credit($operator, $player, $payout, $bet->currency, "payout:{$bet->id}", (int) $bet->id);
            }

            return $settled > 0;
        });

        $bet = $bet->fresh() ?? $bet;
        if ($settled) {
            BetSettled::dispatch($bet);
        }

        return $bet;
    }

    /** The stake wasn't confirmed: the bet never plays. Unanswered stakes are rolled back. */
    private function abandon(Bet $bet, bool $refused): void
    {
        $operator = $bet->operator;
        $player = $bet->player;

        // One transaction with its refund. An unanswered stake is rolled back only after the
        // operator's debit window, so the refund can't overtake a debit still in flight.
        DB::transaction(function () use ($bet, $refused, $operator, $player): void {
            $flipped = DB::table('bets')->where('id', $bet->id)->where('status', BetStatus::Pending->value)->update([
                'status' => ($refused ? BetStatus::Rejected : BetStatus::RolledBack)->value,
                'settled_at' => now(),
                'updated_at' => now(),
            ]);

            if ($flipped > 0 && ! $refused) {
                $this->wallet->rollback($operator, $player, (string) $bet->amount, $bet->currency, "rollback:{$bet->id}", (int) $bet->id,
                    (int) config('wallet.unanswered_debit_rollback_delay_seconds', 60));
            }
        });
    }
}
