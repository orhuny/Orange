<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Exceptions\BettingClosed;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;

class BetPlacementService
{
    public function __construct(private readonly WalletService $wallet) {}

    public function place(
        Operator $operator,
        string $externalPlayerId,
        string $roundUuid,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?float $autoCashoutAt = null,
    ): Bet {
        return DB::transaction(function () use (
            $operator,
            $externalPlayerId,
            $roundUuid,
            $amount,
            $currency,
            $idempotencyKey,
            $autoCashoutAt,
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
                'round_id' => $round->id,
                'player_id' => $player->id,
                'operator_id' => $operator->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => BetStatus::Placed,
                'auto_cashout_at' => $autoCashoutAt,
                'placed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $tx = $this->wallet->debit(
                $operator,
                $player,
                $amount,
                $currency,
                "bet:{$idempotencyKey}",
                $bet->id,
            );

            $bet->forceFill(['external_tx_id' => (string) $tx->id])->save();

            $round->forceFill([
                'total_bet_amount' => bcadd((string) $round->total_bet_amount, $amount, 8),
            ])->save();

            BetPlaced::dispatch($bet);

            return $bet;
        });
    }
}
