<?php

declare(strict_types=1);

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

it('records the operator response when the wallet callback succeeds', function (): void {
    Http::fake([
        '*' => Http::response(['balance_after' => '42.00', 'operator_tx_id' => 'op-1'], 200),
    ]);

    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $tx = app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-success-1');

    expect($tx->operator_callback_status)->toBe(WalletCallbackStatus::Completed)
        ->and($tx->operator_response)->toBeArray()
        ->and($tx->operator_response['body']['balance_after'])->toBe('42.00');

    Http::assertSentCount(1);
});

it('marks the transaction failed and stops on a 4xx callback', function (): void {
    Http::fake([
        '*' => Http::response(['error' => 'insufficient_funds'], 422),
    ]);

    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $tx = app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-4xx-1');

    expect($tx->operator_callback_status)->toBe(WalletCallbackStatus::Failed);

    Http::assertSentCount(1); // 4xx is terminal — no retries
});

it('retries on 5xx and gives up after configured attempts', function (): void {
    config()->set('wallet.callback.retry_attempts', 3);

    Http::fake([
        '*' => Http::sequence()
            ->push('boom', 500)
            ->push('still down', 503)
            ->push('one more', 502),
    ]);

    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $tx = app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-5xx-1');

    expect($tx->operator_callback_status)->toBe(WalletCallbackStatus::Failed);

    Http::assertSentCount(3);
});

it('signs callbacks with the operator HMAC scheme', function (): void {
    Http::fake();

    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-sig-1');

    Http::assertSent(function ($request) use ($operator) {
        return $request->hasHeader('X-Operator-Id', $operator->id)
            && $request->hasHeader('X-Signature')
            && $request->hasHeader('X-Timestamp')
            && $request->hasHeader('Idempotency-Key', 'k-sig-1');
    });
});

it('returns the cached transaction for repeated idempotency keys without hitting HTTP', function (): void {
    Http::fake();
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $first = app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-idem-1');
    $second = app(WalletService::class)->debit($operator, $player, '5.00', 'EUR', 'k-idem-1');

    expect($second->id)->toBe($first->id);
    Http::assertSentCount(1);
});
