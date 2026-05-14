<?php

declare(strict_types=1);

use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\CashoutCompleted;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Enums\WebhookStatus;
use App\Domain\Wallet\Jobs\DeliverWebhookJob;
use App\Domain\Wallet\Models\Webhook;
use App\Domain\Wallet\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Http;

it('WebhookDispatcher creates a pending webhook and queues delivery', function (): void {
    Http::fake();
    $operator = Operator::factory()->create();

    $webhook = app(WebhookDispatcher::class)->dispatch(
        $operator,
        WebhookEvent::BetPlaced,
        ['demo' => true],
    );

    expect($webhook)->toBeInstanceOf(Webhook::class)
        ->and($webhook->status)->toBe(WebhookStatus::Pending)
        ->and($webhook->attempts)->toBe(0);
});

it('DeliverWebhookJob marks webhook delivered on 2xx and signs the request', function (): void {
    Http::fake(['*' => Http::response(['ack' => true], 200)]);

    $operator = Operator::factory()->create();
    $webhook = Webhook::factory()->create([
        'operator_id' => $operator->id,
        'event' => WebhookEvent::CashoutDone,
    ]);

    (new DeliverWebhookJob($webhook->id))->handle();

    $webhook->refresh();
    expect($webhook->status)->toBe(WebhookStatus::Delivered)
        ->and($webhook->attempts)->toBe(1)
        ->and($webhook->delivered_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Signature')
        && $request->hasHeader('X-Webhook-Event', 'cashout.done')
    );
});

it('DeliverWebhookJob sends 4xx straight to DLQ', function (): void {
    Http::fake(['*' => Http::response(['error' => 'bad_signature'], 401)]);

    $webhook = Webhook::factory()->create();

    (new DeliverWebhookJob($webhook->id))->handle();

    $webhook->refresh();
    expect($webhook->status)->toBe(WebhookStatus::DeadLetter)
        ->and($webhook->next_retry_at)->toBeNull();
});

it('DeliverWebhookJob retries on 5xx and DLQs after max attempts', function (): void {
    config()->set('wallet.webhooks.max_attempts', 2);
    config()->set('wallet.webhooks.retry_backoff_seconds', [0, 0]);
    Http::fake(['*' => Http::response('boom', 500)]);

    $webhook = Webhook::factory()->create();

    // QUEUE_CONNECTION=sync in tests, so the self::dispatch retry runs inline.
    (new DeliverWebhookJob($webhook->id))->handle();

    $webhook->refresh();
    expect($webhook->status)->toBe(WebhookStatus::DeadLetter)
        ->and($webhook->attempts)->toBe(2);
});

it('BetPlaced event fans out a bet.placed webhook', function (): void {
    Http::fake();
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $bet = Bet::factory()->create([
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'idempotency_key' => 'evt-bp-1',
    ]);

    BetPlaced::dispatch($bet);

    $webhook = Webhook::query()->where('operator_id', $operator->id)
        ->where('event', WebhookEvent::BetPlaced->value)
        ->first();

    expect($webhook)->not->toBeNull()
        ->and($webhook->payload['bet_id'])->toBe($bet->id);
});

it('CashoutCompleted event fans out a cashout.done webhook', function (): void {
    Http::fake();
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);

    $bet = Bet::factory()->create([
        'operator_id' => $operator->id,
        'player_id' => $player->id,
    ]);
    $cashout = Cashout::factory()->create(['bet_id' => $bet->id]);

    CashoutCompleted::dispatch($cashout);

    $webhook = Webhook::query()
        ->where('operator_id', $operator->id)
        ->where('event', WebhookEvent::CashoutDone->value)
        ->first();

    expect($webhook)->not->toBeNull()
        ->and($webhook->payload['cashout_id'])->toBe($cashout->id);
});
