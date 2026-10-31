<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Support\Carbon;

require_once __DIR__.'/CashoutServiceTest.php';

// Bets record their settlement (multiplier, payout, settled_at), and payouts never exceed max win.

it('records the multiplier, payout and settlement time of a won bet', function (): void {
    $bet = placedBetOnRunningRound();

    $cashout = app(CashoutService::class)->cashout($bet);

    $bet->refresh();
    expect($bet->status)->toBe(BetStatus::Won)
        ->and((string) $bet->multiplier)->toBe(number_format((float) $cashout->multiplier, 4, '.', ''))
        ->and((string) $bet->payout_amount)->toBe((string) $cashout->payout_amount)
        ->and($bet->settled_at)->not->toBeNull();
});

it('records a zero multiplier and payout for a lost bet', function (): void {
    $bet = placedBetOnRunningRound();

    app(RoundOrchestrator::class)->crash($bet->round);

    $bet->refresh();
    expect($bet->status)->toBe(BetStatus::Lost)
        ->and((string) $bet->multiplier)->toBe('0.0000')
        ->and((string) $bet->payout_amount)->toBe('0.00000000')
        ->and($bet->settled_at)->not->toBeNull();
});

it('caps a cashout at the max payout fixed on the bet, and credits only that', function (): void {
    $bet = placedBetOnRunningRound();
    $bet->forceFill(['max_payout' => '15.00000000'])->save();
    // 10.00 at ~20x would pay ~200.
    $bet->round->forceFill(['started_at' => Carbon::now()->subSeconds(50)])->save();

    $cashout = app(CashoutService::class)->cashout($bet->fresh());

    expect((string) $cashout->payout_amount)->toBe('15.00000000')
        ->and((float) $cashout->multiplier)->toBeGreaterThan(1.5)
        ->and((string) $bet->fresh()->payout_amount)->toBe('15.00000000')
        ->and((string) WalletTransaction::query()->where('type', 'credit')->sole()->amount)->toBe('15.00000000')
        ->and((string) $bet->round->fresh()->total_payout_amount)->toBe('15.00000000');
});

it('auto-cashes-out a bet the moment it reaches its max win', function (): void {
    $bet = placedBetOnRunningRound();
    $bet->forceFill(['max_payout' => '25.00000000'])->save(); // 10.00 stake → 2.5x

    MultiplierTick::dispatch($bet->round, 2.49, 15.0);
    expect($bet->fresh()->status)->toBe(BetStatus::Placed);

    MultiplierTick::dispatch($bet->round, 2.50, 15.3);
    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and(Cashout::query()->where('bet_id', $bet->id)->where('is_auto', true)->count())->toBe(1)
        ->and((string) $bet->fresh()->payout_amount)->toBeLessThanOrEqual('25.00000000');
});

it('writes a bet outcome only once', function (): void {
    $bet = Bet::factory()->create();

    $bet->update(['outcome' => ['roll' => '23.45']]);
    $bet->update(['status' => BetStatus::Won]);

    expect(fn () => $bet->update(['outcome' => ['roll' => '99.99']]))->toThrow(LogicException::class)
        ->and($bet->fresh()->outcome)->toBe(['roll' => '23.45']);
});
