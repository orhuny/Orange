<?php

declare(strict_types=1);

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Models\WalletTransaction;
use App\Domain\Wallet\Services\WalletReconciler;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

afterEach(fn () => Carbon::setTestNow());

beforeEach(function (): void {
    config()->set('wallet.callback.retry_attempts', 1);
    config()->set('wallet.callback.retry_backoff_ms', 1);
    config()->set('wallet.reconcile.max_attempts', 3);
    config()->set('wallet.reconcile.backoff_seconds', [60, 120]);
    Http::fake(['wallet.test/*' => fn () => config('testing.wallet_up')
        ? Http::response(['balance_after' => '10.00'], 200)
        : Http::response(['error' => 'unavailable'], 503)]);
});

/** @return array{0: Operator, 1: Player} */
function reconcileParties(): array
{
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/orange']);

    return [$operator, Player::factory()->create(['operator_id' => $operator->id])];
}

// One fake for the whole test whose answer follows a switch: a second Http::fake() would
// only add a stub behind the first one, which keeps matching.
function walletDown(): void
{
    config()->set('testing.wallet_up', false);
}

function walletUp(): void
{
    config()->set('testing.wallet_up', true);
}

it('re-sends a failed credit until the operator acknowledges it, with the same request', function (): void {
    [$operator, $player] = reconcileParties();
    walletDown();
    $tx = app(WalletService::class)->credit($operator, $player, '15.00000000', 'EUR', 'cashout:42');
    expect($tx->operator_callback_status)->toBe(WalletCallbackStatus::Failed);
    $first = Http::recorded()->first()[0];

    walletUp();
    $summary = app(WalletReconciler::class)->run();

    expect($summary)->toBe(['attempted' => 1, 'completed' => 1, 'failed' => 0, 'gave_up' => 0])
        ->and($tx->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Completed)
        ->and($tx->fresh()->reconcile_attempts)->toBe(1);
    Http::assertSent(fn (Request $retry): bool => $retry->url() === $first->url()
        && $retry->data() === $first->data()
        && $retry->header('Idempotency-Key') === $first->header('Idempotency-Key'));
});

it('re-sends a failed rollback, but never a failed debit', function (): void {
    [$operator, $player] = reconcileParties();
    walletDown();
    $debit = app(WalletService::class)->debit($operator, $player, '1.00', 'EUR', 'bet:k1');
    $rollback = app(WalletService::class)->rollback($operator, $player, '1.00000000', 'EUR', 'rollback:7');

    walletUp();
    app(WalletReconciler::class)->run();

    expect($debit->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Failed)
        ->and($debit->fresh()->reconcile_attempts)->toBe(0)
        ->and($rollback->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Completed);
});

it('waits for the backoff before trying again', function (): void {
    [$operator, $player] = reconcileParties();
    walletDown();
    $tx = app(WalletService::class)->credit($operator, $player, '5.00000000', 'EUR', 'cashout:1');

    app(WalletReconciler::class)->run();
    expect($tx->fresh()->reconcile_attempts)->toBe(1)
        ->and(app(WalletReconciler::class)->run()['attempted'])->toBe(0);

    Carbon::setTestNow(now()->addSeconds(61));
    expect(app(WalletReconciler::class)->run()['attempted'])->toBe(1)
        ->and($tx->fresh()->reconcile_attempts)->toBe(2);
});

it('gives up after the maximum number of attempts', function (): void {
    [$operator, $player] = reconcileParties();
    walletDown();
    $tx = app(WalletService::class)->credit($operator, $player, '5.00000000', 'EUR', 'cashout:2');
    $tx->forceFill(['reconcile_attempts' => 2])->save();

    expect(app(WalletReconciler::class)->run())->toBe(['attempted' => 1, 'completed' => 0, 'failed' => 1, 'gave_up' => 1]);

    Carbon::setTestNow(now()->addDay());
    walletUp();
    expect(app(WalletReconciler::class)->run()['attempted'])->toBe(0)
        ->and($tx->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Failed);
});

it('picks up a credit left pending past the grace period, but not a fresh one', function (): void {
    [$operator, $player] = reconcileParties();
    walletUp();
    $stale = WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $player->id, 'type' => 'credit',
        'operator_callback_status' => WalletCallbackStatus::Pending, 'idempotency_key' => 'cashout:3',
        'created_at' => now()->subMinutes(10),
    ]);
    $fresh = WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $player->id, 'type' => 'credit',
        'operator_callback_status' => WalletCallbackStatus::Pending, 'idempotency_key' => 'cashout:4',
    ]);

    app(WalletReconciler::class)->run();

    expect($stale->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Completed)
        ->and($fresh->fresh()->operator_callback_status)->toBe(WalletCallbackStatus::Pending);
});

it('runs as wallet:reconcile and is scheduled every minute', function (): void {
    walletUp();

    artisan('wallet:reconcile')->expectsOutputToContain('0 attempted')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'wallet:reconcile'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
