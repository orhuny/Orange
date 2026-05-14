<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\GameRound;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

class CashoutService
{
    public function __construct(
        private readonly MultiplierCalculator $calculator,
        private readonly WalletService $wallet,
    ) {}

    /**
     * Cash out a single bet at the current server-side multiplier.
     * Race-safe: the UPDATE WHERE status='placed' ensures at most one caller wins.
     */
    public function cashout(Bet $bet, bool $isAuto = false): Cashout
    {
        return DB::transaction(function () use ($bet, $isAuto): Cashout {
            $round = GameRound::query()
                ->whereKey($bet->round_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($round->state !== RoundState::Running) {
                throw RoundNotRunning::for($round->state);
            }

            $multiplier = $this->calculator->forRound($round);

            $affected = DB::table('bets')
                ->where('id', $bet->id)
                ->where('status', BetStatus::Placed->value)
                ->update(['status' => BetStatus::Won->value, 'updated_at' => now()]);

            if ($affected === 0) {
                throw AlreadyCashedOut::for((int) $bet->id);
            }

            $payout = bcmul((string) $bet->amount, number_format($multiplier, 8, '.', ''), 8);

            $cashout = Cashout::create([
                'bet_id' => $bet->id,
                'multiplier' => number_format($multiplier, 2, '.', ''),
                'payout_amount' => $payout,
                'cashed_out_at' => now(),
                'is_auto' => $isAuto,
            ]);

            $this->wallet->credit(
                $bet->operator,
                $bet->player,
                $payout,
                $bet->currency,
                "cashout:{$bet->id}",
                (int) $bet->id,
            );

            $round->forceFill([
                'total_payout_amount' => bcadd((string) $round->total_payout_amount, $payout, 8),
            ])->save();

            CashoutCompleted::dispatch($cashout);

            return $cashout;
        });
    }

    /**
     * Mark every still-`placed` bet on a crashed round as lost.
     * Returns the number of rows affected.
     */
    public function settleLosers(GameRound $round): int
    {
        return DB::table('bets')
            ->where('round_id', $round->id)
            ->where('status', BetStatus::Placed->value)
            ->update(['status' => BetStatus::Lost->value, 'updated_at' => now()]);
    }
}
