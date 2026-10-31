<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\StakeNotTaken;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Services\DebitResult;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

class BetPlacementService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly GameLimits $limits,
    ) {}

    /**
     * @param  bool  $confirmStake  direct play: the bet starts pending and only counts once the
     *                              operator confirms the stake debit (relay play debits in the
     *                              background, as it always has, because the operator already took it)
     *
     * @throws BettingClosed
     * @throws GameNotAvailable
     * @throws CurrencyNotSupported
     * @throws StakeOutsideLimits
     * @throws StakeNotTaken
     */
    public function place(
        Operator $operator,
        string $externalPlayerId,
        string $roundUuid,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?float $autoCashoutAt = null,
        bool $confirmStake = false,
    ): Bet {
        $created = false;

        $bet = DB::transaction(function () use (
            $operator,
            $externalPlayerId,
            $roundUuid,
            $amount,
            $currency,
            $idempotencyKey,
            $autoCashoutAt,
            $confirmStake,
            &$created,
        ): Bet {
            $existing = Bet::query()
                ->where('operator_id', $operator->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof Bet) {
                return $existing;
            }

            $round = GameRound::query()
                ->where('round_uuid', $roundUuid)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $round->state->acceptsBets()) {
                throw BettingClosed::for($round->state);
            }

            // Enablement and per-currency limits, checked before anything is written so a
            // rejected bet leaves no bet and no debit behind (afq refunds on any 4xx).
            $limits = $this->limits->for($round->game, $operator, $currency);
            $this->limits->assertStake($limits, $amount);

            $player = Player::query()->firstOrCreate(
                [
                    'operator_id' => $operator->id,
                    'external_player_id' => $externalPlayerId,
                ],
                [
                    'currency' => $currency,
                    'status' => 'active',
                ],
            );

            $bet = Bet::create([
                'game_id' => $round->game_id,
                'round_id' => $round->id,
                'player_id' => $player->id,
                'operator_id' => $operator->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $confirmStake ? BetStatus::Pending : BetStatus::Placed,
                'auto_cashout_at' => $autoCashoutAt,
                'max_payout' => $limits->maxWin,
                'placed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $created = true;

            $round->forceFill([
                'total_bet_amount' => bcadd((string) $round->total_bet_amount, $amount, 8),
            ])->save();

            if ($confirmStake) {
                // Debited after this transaction commits: no lock is held across the HTTP call.
                return $bet;
            }

            $tx = $this->wallet->debit(
                $operator,
                $player,
                $amount,
                $currency,
                "bet:{$idempotencyKey}",
                $bet->id,
            );

            $bet->forceFill(['external_tx_id' => (string) $tx->id])->save();

            BetPlaced::dispatch($bet);

            return $bet;
        });

        return $confirmStake && $created ? $this->confirmStake($operator, $bet, $amount) : $bet;
    }

    /**
     * Debits the stake of a pending bet and waits for the operator. Accepted: the bet goes
     * live (or, if its round ended meanwhile, counts as lost). Refused: the bet is rejected.
     * Unanswered: the bet is rolled back and a rollback is sent. Either way a bet whose
     * stake wasn't taken never plays.
     *
     * @throws StakeNotTaken
     */
    private function confirmStake(Operator $operator, Bet $bet, string $amount): Bet
    {
        $result = $this->wallet->debitNow($operator, $bet->player, $amount, $bet->currency, "bet:{$bet->idempotency_key}", (int) $bet->id);

        if ($result->accepted()) {
            DB::transaction(function () use ($bet, $result): void {
                // Locking the round orders this with RoundOrchestrator::crash(), which settles
                // losers under the same lock: either it sees the bet placed, or we see it crashed.
                $round = GameRound::query()->whereKey($bet->round_id)->lockForUpdate()->first();

                DB::table('bets')->where('id', $bet->id)->where('status', BetStatus::Pending->value)
                    ->update(['status' => BetStatus::Placed->value, 'external_tx_id' => (string) $result->transaction->id, 'updated_at' => now()]);

                if ($round instanceof GameRound && ! in_array($round->getAttribute('state'), [RoundState::Betting, RoundState::Running], true)) {
                    DB::table('bets')->where('id', $bet->id)->where('status', BetStatus::Placed->value)
                        ->update(['status' => BetStatus::Lost->value, 'multiplier' => '0', 'payout_amount' => '0', 'settled_at' => now(), 'updated_at' => now()]);
                }
            });

            $bet = $bet->fresh() ?? $bet;
            BetPlaced::dispatch($bet);

            return $bet;
        }

        $refused = $result->outcome === DebitResult::REJECTED;
        $flipped = DB::table('bets')->where('id', $bet->id)->where('status', BetStatus::Pending->value)->update([
            'status' => ($refused ? BetStatus::Rejected : BetStatus::RolledBack)->value,
            'settled_at' => now(),
            'updated_at' => now(),
        ]);

        if ($flipped > 0) {
            DB::update('UPDATE game_rounds SET total_bet_amount = total_bet_amount - ? WHERE id = ?', [$amount, $bet->round_id]);

            if (! $refused) {
                $this->wallet->rollback($operator, $bet->player, $amount, $bet->currency, "rollback:{$bet->id}", (int) $bet->id);
            }
        }

        throw $refused ? StakeNotTaken::refused($result->error) : StakeNotTaken::unanswered();
    }
}
