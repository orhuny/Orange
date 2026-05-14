<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Support\Carbon;

function placedBetOnRunningRound(?float $autoCashoutAt = null): Bet
{
    $game = Game::factory()->create();
    $orch = app(RoundOrchestrator::class);

    $round = $orch->create($game);
    $round = $orch->openBetting($round);
    $round = $orch->start($round);

    // Make sure crash_point is comfortably high so the bet would normally win.
    $round->forceFill(['crash_point' => 100.00, 'started_at' => Carbon::now()->subSeconds(2)])->save();

    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    return Bet::factory()->create([
        'round_id' => $round->id,
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'amount' => '10.00000000',
        'currency' => 'EUR',
        'status' => BetStatus::Placed,
        'auto_cashout_at' => $autoCashoutAt,
        'placed_at' => now(),
        'idempotency_key' => 'k-'.uniqid(),
    ]);
}

it('manual cashout transitions the bet to won and writes a cashout row', function (): void {
    $bet = placedBetOnRunningRound();
    $service = app(CashoutService::class);

    $cashout = $service->cashout($bet);

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($cashout->bet_id)->toBe($bet->id)
        ->and((float) $cashout->multiplier)->toBeGreaterThanOrEqual(1.0)
        ->and((float) $cashout->payout_amount)->toBeGreaterThan(0.0)
        ->and($cashout->is_auto)->toBeFalse();

    expect(WalletTransaction::query()->where('bet_id', $bet->id)->where('type', 'credit')->count())->toBe(1);
});

it('refuses to cash out when the round is not running', function (): void {
    $bet = placedBetOnRunningRound();
    GameRound::query()->where('id', $bet->round_id)->update(['state' => RoundState::Crashed]);

    $service = app(CashoutService::class);

    expect(fn () => $service->cashout($bet))
        ->toThrow(RoundNotRunning::class);
});

it('only one of 100 racing cashouts succeeds (atomic UPDATE WHERE)', function (): void {
    $bet = placedBetOnRunningRound();
    $service = app(CashoutService::class);

    $successes = 0;
    $failures = 0;

    foreach (range(1, 100) as $_) {
        try {
            $service->cashout($bet);
            $successes++;
        } catch (AlreadyCashedOut) {
            $failures++;
        }
    }

    expect($successes)->toBe(1)
        ->and($failures)->toBe(99)
        ->and(Cashout::query()->where('bet_id', $bet->id)->count())->toBe(1);
});

it('settleLosers marks every still-placed bet on the round as lost', function (): void {
    $service = app(CashoutService::class);
    $bet = placedBetOnRunningRound();
    $bet2 = placedBetOnRunningRound();
    Bet::query()->whereKey($bet2->id)->update(['round_id' => $bet->round_id]);

    $round = $bet->round;

    $service->cashout($bet); // bet1 wins
    $affected = $service->settleLosers($round->fresh());

    expect($affected)->toBe(1)
        ->and($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and($bet2->fresh()->status)->toBe(BetStatus::Lost);
});

it('crashing the round via RoundOrchestrator settles all placed bets as lost', function (): void {
    $bet = placedBetOnRunningRound();
    $orch = app(RoundOrchestrator::class);
    $round = $bet->round;

    $orch->crash($round);

    expect($bet->fresh()->status)->toBe(BetStatus::Lost);
});
