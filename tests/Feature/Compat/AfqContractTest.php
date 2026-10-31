<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\BettingOpened;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundCrashed;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Game\Events\RoundStarted;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Broadcasting\Channel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\artisan;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/*
 * The live afq integration (afq-integrations + afq-casino), byte for byte as afq sends it.
 * OrangeConsumer and OrangeApp use the same contract. These tests pin what afq relies on:
 * request formats, response keys, error codes, wallet callback tx ids, webhook and broadcast
 * payloads, and the /crash bridge. A change that breaks one of them breaks production.
 */

const AFQ_CALLBACK_BASE = 'https://afq.test/dd';

function afqOperator(): Operator
{
    Cache::flush();

    return Operator::factory()->create(['callback_base_url' => AFQ_CALLBACK_BASE]);
}

function afqBettingRound(): GameRound
{
    $game = Game::query()->where('code', 'crash')->first() ?? Game::factory()->create(['code' => 'crash']);
    $orchestrator = app(RoundOrchestrator::class);

    return $orchestrator->openBetting($orchestrator->create($game));
}

/**
 * Signs and sends a request exactly like afq's OrangeCasinoService::callOrange().
 *
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function afqCall(Operator $operator, string $path, array $payload, string $idempotencyKey, ?int $timestamp = null): TestResponse
{
    $body = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
    $timestamp ??= time();

    $headers = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-Operator-Id' => $operator->id,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => OperatorSignature::compute($operator->secret_key, 'POST', $path, $body, $timestamp),
        'Idempotency-Key' => $idempotencyKey,
    ];
    $server = [];
    foreach ($headers as $name => $value) {
        $key = strtoupper(str_replace('-', '_', $name));
        $server[in_array($key, ['CONTENT_TYPE'], true) ? $key : 'HTTP_'.$key] = $value;
    }

    return call('POST', $path, [], [], [], $server, $body);
}

/**
 * @param  list<string>  $keys
 * @return list<string>
 */
function afqSorted(array $keys): array
{
    sort($keys);

    return $keys;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function afqBetPayload(GameRound $round, array $overrides = []): array
{
    // afq drops null fields (array_filter), so auto_cashout_at is absent unless set.
    return array_merge([
        'round_uuid' => $round->round_uuid,
        'player_external_id' => '12*3456',
        'amount' => '1.00',
        'currency' => 'EUR',
    ], $overrides);
}

/** @return list<Request> */
function afqWalletCalls(string $type): array
{
    return Http::recorded(fn (Request $r) => $r->url() === AFQ_CALLBACK_BASE.'/wallet/'.$type)
        ->map(fn (array $pair) => $pair[0])->values()->all();
}

/** @return list<Request> */
function afqWebhooks(string $event): array
{
    return Http::recorded(fn (Request $r) => $r->url() === AFQ_CALLBACK_BASE.'/webhooks' && $r->header('X-Webhook-Event') === [$event])
        ->map(fn (array $pair) => $pair[0])->values()->all();
}

function afqAssertSignedByOrange(Request $request, Operator $operator): void
{
    $path = (string) parse_url($request->url(), PHP_URL_PATH);
    $expected = OperatorSignature::compute($operator->secret_key, 'POST', $path, $request->body(), (int) $request->header('X-Timestamp')[0]);

    expect($request->header('X-Operator-Id'))->toBe([$operator->id])
        ->and($request->header('X-Signature'))->toBe([$expected]);
}

beforeEach(function (): void {
    config()->set('wallet.callback.retry_backoff_ms', 1);
    Http::fake([
        'afq.test/*' => Http::response(['balance_after' => '99.00', 'operator_tx_id' => 'afq_1'], 200),
    ]);
});

it('places a bet: response keys, debit callback with tx_id bet:{key}, bet.placed webhook, idempotent retry', function (): void {
    $operator = afqOperator();
    $round = afqBettingRound();
    $key = 'f1e2d3c4-0000-4000-8000-000000000001';

    $response = afqCall($operator, '/v1/bets', afqBetPayload($round), $key);

    $response->assertStatus(201);
    expect(array_keys($response->json()))->toEqualCanonicalizing(['bet_id', 'status', 'round_uuid', 'amount', 'currency', 'wallet_tx_id'])
        ->and($response->json('status'))->toBe('placed')
        ->and($response->json('round_uuid'))->toBe($round->round_uuid)
        ->and($response->json('amount'))->toBe('1.00000000')
        ->and($response->json('currency'))->toBe('EUR');
    $betId = $response->json('bet_id');

    $debits = afqWalletCalls('debit');
    expect($debits)->toHaveCount(1);
    afqAssertSignedByOrange($debits[0], $operator);
    expect($debits[0]->header('Idempotency-Key'))->toBe(["bet:{$key}"])
        ->and(array_keys($debits[0]->data()))->toEqualCanonicalizing(['player_external_id', 'amount', 'currency', 'reference', 'tx_id'])
        ->and($debits[0]['tx_id'])->toBe("bet:{$key}")
        ->and($debits[0]['player_external_id'])->toBe('12*3456')
        ->and(bccomp((string) $debits[0]['amount'], '1', 8))->toBe(0)
        ->and($debits[0]['currency'])->toBe('EUR')
        ->and($debits[0]['reference']['bet_id'])->toBe($betId);

    $webhooks = afqWebhooks('bet.placed');
    expect($webhooks)->toHaveCount(1)
        ->and($webhooks[0]['event'])->toBe('bet.placed')
        ->and(array_keys($webhooks[0]['data']))->toEqualCanonicalizing(['bet_id', 'round_uuid', 'amount', 'currency', 'placed_at']);

    // afq retries with the same Idempotency-Key and a fresh timestamp.
    $retry = afqCall($operator, '/v1/bets', afqBetPayload($round), $key, time() + 1);
    $retry->assertStatus(201)->assertHeader('Idempotent-Replay', 'true');
    expect($retry->json('bet_id'))->toBe($betId)
        ->and(Bet::count())->toBe(1)
        ->and(afqWalletCalls('debit'))->toHaveCount(1);
});

it('cashes out with an empty body: response keys, credit callback matching the payout, cashout.done webhook', function (): void {
    $operator = afqOperator();
    $round = afqBettingRound();
    $betId = afqCall($operator, '/v1/bets', afqBetPayload($round), 'afq-bet-cashout')->json('bet_id');

    Carbon::setTestNow(now());
    $round = app(RoundOrchestrator::class)->start($round);
    Carbon::setTestNow(now()->addSeconds(5));

    $response = afqCall($operator, "/v1/bets/{$betId}/cashout", [], 'afq-cashout-1');

    $response->assertOk();
    expect(array_keys($response->json()))->toEqualCanonicalizing(['cashout_id', 'bet_id', 'multiplier', 'payout_amount', 'cashed_out_at', 'is_auto'])
        ->and($response->json('bet_id'))->toBe($betId)
        ->and($response->json('is_auto'))->toBeFalse()
        ->and($response->json('payout_amount'))->toBeString();

    // afq credits the payout itself under the same tx id, so the amounts must agree.
    $credits = afqWalletCalls('credit');
    expect($credits)->toHaveCount(1);
    afqAssertSignedByOrange($credits[0], $operator);
    expect($credits[0]['tx_id'])->toBe("cashout:{$betId}")
        ->and($credits[0]->header('Idempotency-Key'))->toBe(["cashout:{$betId}"])
        ->and($credits[0]['reference']['bet_id'])->toBe($betId)
        ->and(bccomp((string) $credits[0]['amount'], (string) $response->json('payout_amount'), 8))->toBe(0);

    $webhooks = afqWebhooks('cashout.done');
    expect($webhooks)->toHaveCount(1)
        ->and(array_keys($webhooks[0]['data']))->toEqualCanonicalizing(['cashout_id', 'bet_id', 'multiplier', 'payout_amount', 'is_auto', 'cashed_out_at']);

    // A second cashout (afq's manual click racing its auto-cashout) reports the settled win.
    // (one second later: an identical empty-body request in the same second is rejected as a replay)
    $again = afqCall($operator, "/v1/bets/{$betId}/cashout", [], 'afq-cashout-2', time() + 1);
    $again->assertStatus(409);
    expect(array_keys($again->json()))->toEqualCanonicalizing(['error', 'bet_status', 'cashout'])
        ->and($again->json('error'))->toBe('already_settled')
        ->and($again->json('bet_status'))->toBe('won')
        ->and(array_keys($again->json('cashout')))->toEqualCanonicalizing(['cashout_id', 'bet_id', 'multiplier', 'payout_amount', 'cashed_out_at', 'is_auto']);

    Carbon::setTestNow();
});

it('answers every rejected bet with a 4xx that leaves no bet and no debit (afq refunds on 4xx)', function (): void {
    $operator = afqOperator();
    $round = afqBettingRound();

    $unknown = afqCall($operator, '/v1/bets', afqBetPayload($round, ['round_uuid' => '00000000-0000-4000-8000-000000000000']), 'afq-unknown');
    $unknown->assertStatus(404)->assertExactJson(['error' => 'unknown_round']);

    $invalid = afqCall($operator, '/v1/bets', afqBetPayload($round, ['amount' => '-1']), 'afq-invalid');
    $invalid->assertStatus(422);

    $badSignature = afqCall($operator, '/v1/bets', afqBetPayload($round), 'afq-bad-sig', time() + 7200);
    $badSignature->assertStatus(401)->assertJson(['error' => 'unauthorized', 'reason' => 'timestamp_outside_window']);

    $running = app(RoundOrchestrator::class)->start($round);
    $closed = afqCall($operator, '/v1/bets', afqBetPayload($running), 'afq-closed');
    $closed->assertStatus(409)->assertJsonPath('error', 'betting_closed');
    expect($closed->json('message'))->toBeString();

    expect(Bet::count())->toBe(0)
        ->and(afqWalletCalls('debit'))->toHaveCount(0);
});

it('rejects a cashout outside the running phase with the codes afq maps to round_crashed', function (): void {
    $operator = afqOperator();
    $round = afqBettingRound();
    $betId = afqCall($operator, '/v1/bets', afqBetPayload($round), 'afq-bet-early')->json('bet_id');

    // Still betting: round_not_running with a detail string.
    $early = afqCall($operator, "/v1/bets/{$betId}/cashout", [], 'afq-early');
    $early->assertStatus(409)->assertJsonPath('error', 'round_not_running');
    expect($early->json('detail'))->toBeString();

    // Crashed: the bet was settled as lost.
    $orchestrator = app(RoundOrchestrator::class);
    $orchestrator->crash($orchestrator->start($round));
    $late = afqCall($operator, "/v1/bets/{$betId}/cashout", [], 'afq-late', time() + 1);
    $late->assertStatus(409)
        ->assertJsonPath('error', 'already_settled')
        ->assertJsonPath('bet_status', 'lost')
        ->assertJsonPath('cashout', null);

    expect(afqWalletCalls('credit'))->toHaveCount(0);
});

it('broadcasts the round lifecycle on round.{uuid} and crash with unchanged names and payload keys', function (): void {
    Event::fake([BettingOpened::class, RoundStarted::class, MultiplierTick::class, RoundCrashed::class, RoundSettled::class]);
    $orchestrator = app(RoundOrchestrator::class);
    $round = afqBettingRound();
    $round = $orchestrator->start($round);
    artisan('crash:tick', ['--once' => true])->assertSuccessful();
    $orchestrator->settle($orchestrator->crash($round));

    $expected = [
        BettingOpened::class => ['round.betting_opened', ['round_uuid', 'server_seed_hash', 'nonce', 'betting_opened_at', 'betting_seconds']],
        RoundStarted::class => ['round.started', ['round_uuid', 'started_at', 'growth_rate']],
        MultiplierTick::class => ['round.tick', ['round_uuid', 'multiplier', 'elapsed_seconds']],
        RoundCrashed::class => ['round.crashed', ['round_uuid', 'crash_point', 'crashed_at']],
        RoundSettled::class => ['round.settled', ['round_uuid', 'settled_at', 'server_seed', 'crash_point', 'next_round_in_seconds']],
    ];

    foreach ($expected as $class => [$name, $keys]) {
        Event::assertDispatched($class, function (BettingOpened|RoundStarted|MultiplierTick|RoundCrashed|RoundSettled $event) use ($name, $keys, $round): bool {
            $channels = array_map(fn (Channel $c) => $c->name, $event->broadcastOn());

            return $event->broadcastAs() === $name
                && $channels === ["round.{$round->round_uuid}", 'crash']
                && afqSorted(array_keys($event->broadcastWith())) === afqSorted($keys);
        });
    }
});

it('reveals a seed at settle that verifies the round through /v1/fairness/verify', function (): void {
    $opened = null;
    $settled = null;
    Event::listen(BettingOpened::class, function (BettingOpened $e) use (&$opened): void {
        $opened = $e->broadcastWith();
    });
    Event::listen(RoundSettled::class, function (RoundSettled $e) use (&$settled): void {
        $settled = $e->broadcastWith();
    });
    $orchestrator = app(RoundOrchestrator::class);
    $round = afqBettingRound();
    $round = $orchestrator->settle($orchestrator->crash($orchestrator->start($round)));

    $verify = postJson('/v1/fairness/verify', [
        'server_seed' => $settled['server_seed'],
        'client_seed' => config('provably_fair.house_client_seed'),
        'nonce' => $opened['nonce'],
        'expected_server_seed_hash' => $opened['server_seed_hash'],
        'house_edge' => $round->game->house_edge,
    ]);

    $verify->assertOk();
    expect(array_keys($verify->json()))->toEqualCanonicalizing(['algorithm', 'house_edge_divisor', 'server_seed_hash', 'expected_hash_matches', 'hmac', 'crash_point'])
        ->and($verify->json('expected_hash_matches'))->toBeTrue()
        ->and((float) $verify->json('crash_point'))->toBe((float) $settled['crash_point']);
});

it('sends round.crashed and round.settled webhooks with unchanged keys to operators with bets', function (): void {
    $operator = afqOperator();
    $round = afqBettingRound();
    afqCall($operator, '/v1/bets', afqBetPayload($round), 'afq-bet-webhooks')->assertStatus(201);

    $orchestrator = app(RoundOrchestrator::class);
    $orchestrator->settle($orchestrator->crash($orchestrator->start($round)));

    $crashed = afqWebhooks('round.crashed');
    $settled = afqWebhooks('round.settled');
    expect($crashed)->toHaveCount(1)
        ->and(array_keys($crashed[0]['data']))->toEqualCanonicalizing(['round_uuid', 'crash_point', 'crashed_at'])
        ->and($settled)->toHaveCount(1)
        ->and(array_keys($settled[0]['data']))->toEqualCanonicalizing(['round_uuid', 'crash_point', 'server_seed', 'settled_at'])
        ->and($settled[0]['data']['server_seed'])->toBeString();
    expect($round->fresh()->state)->toBe(RoundState::Settled);
});

it('serves /crash embeddable by allow-listed origins with the full postMessage bridge', function (): void {
    config()->set('embed.allowed_origins', ['https://afq.example']);

    $response = get('/crash');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self' https://afq.example")
        ->and($response->headers->has('X-Frame-Options'))->toBeFalse();

    $html = $response->getContent();
    $bridgeMessages = [
        // iframe → parent
        'embed_ready', 'place_bet', 'cashout', 'enter_fullscreen', 'exit_fullscreen',
        // parent → iframe
        'player_info', 'bet_placed_result', 'cashout_result', 'fullscreen_state', 'balance_update',
        'round_state', 'auto_cashout_fired',
    ];
    foreach ($bridgeMessages as $message) {
        expect($html)->toContain("'{$message}'");
    }
    expect($html)->toContain("channel('crash')")
        ->and($html)->toContain('https:\/\/afq.example');
    foreach (['.round.betting_opened', '.round.started', '.round.tick', '.round.crashed', '.round.settled'] as $event) {
        expect($html)->toContain("'{$event}'");
    }
});
