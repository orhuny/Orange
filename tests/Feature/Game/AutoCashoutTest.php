<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Models\Cashout;

require_once __DIR__.'/CashoutServiceTest.php';

it('auto-cashes-out bets whose threshold has been crossed by a tick', function (): void {
    $bet = placedBetOnRunningRound(autoCashoutAt: 1.50);

    MultiplierTick::dispatch($bet->round, 1.51, 7.0);

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and(Cashout::where('bet_id', $bet->id)->where('is_auto', true)->count())->toBe(1);
});

it('does not auto-cash-out bets whose threshold is still in the future', function (): void {
    $bet = placedBetOnRunningRound(autoCashoutAt: 5.00);

    MultiplierTick::dispatch($bet->round, 1.20, 1.0);

    expect($bet->fresh()->status)->toBe(BetStatus::Placed)
        ->and(Cashout::where('bet_id', $bet->id)->count())->toBe(0);
});

it('ignores bets without an auto_cashout_at threshold', function (): void {
    $bet = placedBetOnRunningRound(autoCashoutAt: null);

    MultiplierTick::dispatch($bet->round, 99.0, 50.0);

    expect($bet->fresh()->status)->toBe(BetStatus::Placed);
});
