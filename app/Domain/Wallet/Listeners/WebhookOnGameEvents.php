<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Listeners;

use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Events\RoundCrashed;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Services\WebhookDispatcher;

class WebhookOnGameEvents
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function onBetPlaced(BetPlaced $event): void
    {
        $bet = $event->bet;
        $this->dispatcher->dispatch(
            $bet->operator,
            WebhookEvent::BetPlaced,
            [
                'bet_id' => $bet->id,
                'round_uuid' => $bet->round?->round_uuid,
                'amount' => (string) $bet->amount,
                'currency' => $bet->currency,
                'placed_at' => $bet->placed_at?->toIso8601String(),
            ],
        );
    }

    public function onCashoutCompleted(CashoutCompleted $event): void
    {
        $cashout = $event->cashout;
        $bet = $cashout->bet;

        $this->dispatcher->dispatch(
            $bet->operator,
            WebhookEvent::CashoutDone,
            [
                'cashout_id' => $cashout->id,
                'bet_id' => $bet->id,
                'multiplier' => (float) $cashout->multiplier,
                'payout_amount' => (string) $cashout->payout_amount,
                'is_auto' => $cashout->is_auto,
                'cashed_out_at' => $cashout->cashed_out_at?->toIso8601String(),
            ],
        );
    }

    public function onRoundCrashed(RoundCrashed $event): void
    {
        $round = $event->round;

        // Round-level webhooks fan out to every operator that had a bet on the round.
        $operatorIds = $round->bets()->distinct()->pluck('operator_id');
        foreach ($operatorIds as $operatorId) {
            $operator = Operator::find($operatorId);
            if ($operator) {
                $this->dispatcher->dispatch($operator, WebhookEvent::RoundCrashed, [
                    'round_uuid' => $round->round_uuid,
                    'crash_point' => (float) $round->crash_point,
                    'crashed_at' => $round->crashed_at?->toIso8601String(),
                ]);
            }
        }
    }

    public function onRoundSettled(RoundSettled $event): void
    {
        $round = $event->round;

        $operatorIds = $round->bets()->distinct()->pluck('operator_id');
        foreach ($operatorIds as $operatorId) {
            $operator = Operator::find($operatorId);
            if ($operator) {
                $this->dispatcher->dispatch($operator, WebhookEvent::RoundSettled, [
                    'round_uuid' => $round->round_uuid,
                    'crash_point' => (float) $round->crash_point,
                    'server_seed' => $round->serverSeed?->server_seed,
                    'settled_at' => $round->settled_at?->toIso8601String(),
                ]);
            }
        }
    }
}
