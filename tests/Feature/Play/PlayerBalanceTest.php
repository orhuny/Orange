<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Jobs\DispatchWalletCallbackJob;
use App\Domain\Wallet\Models\WalletBalanceCheck;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// The balance the games show is always one the operator's wallet reported, and after a win it
// is the balance with the win paid in.

beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
    config()->set('wallet.payout.retry_backoff_ms', 1);
});
afterEach(fn () => Carbon::setTestNow());

/**
 * A direct-play session on dice, with the published seed pair: the first roll is 44.30, so
 * "under 50" wins 1.98× (a 1.00 stake pays 1.98).
 *
 * @param  array<string, mixed>  $config  the operator's config (e.g. wallet_balance)
 * @return array{0: Operator, 1: PlaySession, 2: array<string, string>}
 */
function balanceOnDice(array $config = []): array
{
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w', 'config' => $config]);
    $game = Game::factory()->create([
        'code' => 'dice', 'slug' => 'dice', 'type' => 'dice', 'access' => GameAccess::Listed,
        'rtp_target' => 0.99, 'house_edge' => 0.01, 'config' => ['min_chance' => '0.01', 'max_chance' => '98.00'],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id, 'settings' => ['play_mode' => 'direct']]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.10', 'max_bet' => '100', 'max_win' => '10000']);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'orange-dice-seed-0001', 'server_seed_hash' => hash('sha256', 'orange-dice-seed-0001'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);
    [$session, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);

    return [$operator, $session, ['Authorization' => "Bearer {$bearer}"]];
}

/** The operator's wallet: 50.00 before the bet, 49.00 after the 1.00 stake, 50.98 with the win. */
function walletAnswering(): void
{
    Http::fake([
        'wallet.test/w/wallet/debit' => Http::response(['balance_after' => '49.00'], 200),
        'wallet.test/w/wallet/credit' => Http::response(['balance_after' => '50.98'], 200),
    ]);
}

/**
 * @param  array<string, string>  $auth
 * @return TestResponse<Response>
 */
function rollUnder50(array $auth, string $key = 'page-key-bal-01'): TestResponse
{
    return postJson('/play-api/games/dice/bets', ['amount' => '1.00', 'params' => ['target' => '50', 'direction' => 'under']], $auth + ['Idempotency-Key' => $key]);
}

it('pays an instant win in before answering, and answers with the wallet\'s balance after it', function (): void {
    // No queue worker runs: the win's balance can't depend on one.
    Queue::fake();
    walletAnswering();
    [, , $auth] = balanceOnDice();

    rollUnder50($auth)
        ->assertCreated()
        ->assertJsonPath('status', 'won')
        ->assertJsonPath('payout_amount', '1.98000000')
        // The wallet's own figures: after the stake, and after the win.
        ->assertJsonPath('balance_after_stake', '49.00')
        ->assertJsonPath('player_balance', '50.98')
        ->assertJsonPath('balance_pending', false);

    $credit = WalletTransaction::query()->where('type', WalletTransactionType::Credit)->sole();
    expect($credit->getAttribute('operator_callback_status'))->toBe(WalletCallbackStatus::Completed)
        ->and($credit->getAttribute('answered_at'))->not->toBeNull();
    Queue::assertNotPushed(DispatchWalletCallbackJob::class);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit') && $r['tx_id'] === 'payout:'.Bet::sole()->id);
});

it('says the balance is pending when the wallet does not answer the win in time, and the queue takes over', function (): void {
    $queue = Queue::fake();
    config()->set('testing.wallet_up', false);
    Http::fake(fn (Request $r) => match (true) {
        str_ends_with($r->url(), '/wallet/debit') => Http::response(['balance_after' => '49.00'], 200),
        config('testing.wallet_up') === true => Http::response(['balance_after' => '50.98'], 200),
        default => Http::response(['error' => 'unavailable'], 503),
    });
    [, , $auth] = balanceOnDice();

    rollUnder50($auth)
        ->assertCreated()
        ->assertJsonPath('status', 'won')
        // The newest figure the wallet reported is the one after the stake.
        ->assertJsonPath('player_balance', '49.00')
        ->assertJsonPath('balance_pending', true);
    getJson('/play-api/balance', $auth)->assertOk()
        ->assertJsonPath('balance', '49.00')
        ->assertJsonPath('pending', true);

    // Two attempts within the deadline, then the queue (same tx_id).
    expect(Http::recorded(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit'))->count())->toBe(2);
    $credit = WalletTransaction::query()->where('type', WalletTransactionType::Credit)->sole();
    expect($credit->getAttribute('operator_callback_status'))->toBe(WalletCallbackStatus::Pending);
    Queue::assertPushed(DispatchWalletCallbackJob::class, fn (DispatchWalletCallbackJob $job): bool => $job->payload['idempotency_key'] === $credit->idempotency_key);

    // The wallet is back; the queued credit goes through.
    config()->set('testing.wallet_up', true);
    $queue->pushed(DispatchWalletCallbackJob::class)->each(fn (DispatchWalletCallbackJob $job) => app()->call([$job, 'handle']));

    getJson('/play-api/balance', $auth)->assertOk()
        ->assertJsonPath('balance', '50.98')
        ->assertJsonPath('pending', false)
        ->assertJsonPath('currency', 'EUR')
        ->assertJsonPath('wallet_tx_id', $credit->id);
});

it('gives a crash win\'s balance once the queued credit is answered', function (): void {
    $queue = Queue::fake();
    Http::fake([
        'wallet.test/w/wallet/debit' => Http::response(['balance_after' => '98.00'], 200),
        'wallet.test/w/wallet/credit' => Http::response(['balance_after' => '102.00'], 200),
    ]);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->openBetting($orchestrator->create($game));
    if ($game->getAttribute('access') === GameAccess::All) {
        OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    }
    [, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);
    $auth = ['Authorization' => "Bearer {$bearer}"];

    $betId = postJson('/play-api/games/crash/bets', ['amount' => '2.00'], $auth + ['Idempotency-Key' => 'page-key-crash-1'])
        ->assertCreated()
        ->assertJsonPath('player_balance', '98.00')
        ->assertJsonPath('balance_pending', false)
        ->json('bet_id');
    Carbon::setTestNow(now());
    $orchestrator->start($round->fresh());
    Carbon::setTestNow(now()->addSeconds(5));

    // The cash-out answers at once; its credit waits in the queue.
    postJson("/play-api/bets/{$betId}/cashout", [], $auth)->assertOk()
        ->assertJsonPath('player_balance', '98.00')
        ->assertJsonPath('balance_pending', true);
    getJson('/play-api/balance', $auth)->assertJsonPath('pending', true);

    $queue->pushed(DispatchWalletCallbackJob::class)->each(fn (DispatchWalletCallbackJob $job) => app()->call([$job, 'handle']));

    getJson('/play-api/balance', $auth)->assertOk()
        ->assertJsonPath('balance', '102.00')
        ->assertJsonPath('pending', false);
});

it('shows the newest answer, not the newest call: a win answered after the next stake', function (): void {
    [$operator, $session, $auth] = balanceOnDice();
    $answered = fn (string $type, string $balance, string $at) => WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $session->player_id, 'type' => $type,
        'operator_callback_status' => WalletCallbackStatus::Completed,
        'operator_response' => ['status' => 200, 'body' => ['balance_after' => $balance]],
    ])->forceFill(['answered_at' => $at])->save();

    // A crash win (the older call) is answered from the queue a moment after the next stake.
    $answered('credit', '120.00', '2026-11-20 10:00:01.900000');
    $answered('debit', '99.00', '2026-11-20 10:00:01.400000');

    getJson('/play-api/balance', $auth)->assertOk()->assertJsonPath('balance', '120.00');
});

it('is not pending for a refused stake, or for anything before this session', function (): void {
    [$operator, $session, $auth] = balanceOnDice();
    $make = fn (string $type, WalletCallbackStatus $status) => WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $session->player_id, 'type' => $type, 'operator_callback_status' => $status,
    ]);

    // A credit from an earlier session that is still being re-sent.
    $make('credit', WalletCallbackStatus::Failed)->forceFill(['created_at' => now()->subDay()])->save();
    // A stake the wallet refused in this one: final, not pending.
    $make('debit', WalletCallbackStatus::Failed);

    getJson('/play-api/balance', $auth)->assertOk()->assertJsonPath('pending', false);
    getJson('/play-api/session', $auth)->assertOk()->assertJsonPath('balance_pending', false);

    // A win of this session that the wallet hasn't answered yet.
    $make('credit', WalletCallbackStatus::Failed);

    getJson('/play-api/balance', $auth)->assertOk()->assertJsonPath('pending', true);
});

it('never passes on a balance that is not a plain decimal', function (): void {
    [$operator, $session, $auth] = balanceOnDice();
    WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $session->player_id,
        'operator_callback_status' => WalletCallbackStatus::Completed,
        'operator_response' => ['status' => 200, 'body' => ['balance_after' => '1e9']],
    ])->forceFill(['answered_at' => now()->format('Y-m-d H:i:s.u')])->save();

    getJson('/play-api/balance', $auth)->assertOk()->assertJsonPath('balance', null);
});

it('asks the operator\'s wallet for the balance as a game opens, when it answers that', function (): void {
    Http::fake([
        'wallet.test/w/wallet/balance' => Http::response(['balance' => '250.00', 'currency' => 'EUR'], 200),
        'wallet.test/w/wallet/debit' => Http::response(['balance_after' => '249.00'], 200),
        'wallet.test/w/wallet/credit' => Http::response(['balance_after' => '250.98'], 200),
    ]);
    [$operator, $session, $auth] = balanceOnDice(['wallet_balance' => '1']);
    // The player's last bet, before this session: since then they deposited at the casino.
    WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $session->player_id,
        'operator_callback_status' => WalletCallbackStatus::Completed,
        'operator_response' => ['status' => 200, 'body' => ['balance_after' => '3.00']],
    ])->forceFill(['answered_at' => now()->subHour()->format('Y-m-d H:i:s.u')])->save();

    getJson('/play-api/session', $auth)->assertOk()
        ->assertJsonPath('balance', '250.00')
        // The wallet's own figure, from now: the casino page's postMessage figure isn't needed.
        ->assertJsonPath('balance_before_launch', false);

    Http::assertSent(function (Request $r) use ($operator): bool {
        $path = '/w/wallet/balance';
        $timestamp = (int) $r->header('X-Timestamp')[0];

        return $r->url() === "https://wallet.test{$path}"
            && $r->data() === ['player_external_id' => 'p-1', 'currency' => 'EUR']
            && $r->header('X-Operator-Id')[0] === $operator->id
            && $r->header('X-Signature')[0] === OperatorSignature::compute($operator->secret_key, 'POST', $path, $r->body(), $timestamp);
    });
    $check = WalletBalanceCheck::sole();
    expect($check->status)->toBe(WalletBalanceCheck::ANSWERED)
        ->and($check->balance)->toBe('250.00')
        ->and($check->currency)->toBe('EUR');
    getJson('/play-api/balance', $auth)->assertJsonPath('balance_check_id', $check->id)->assertJsonPath('wallet_tx_id', null);

    // A bet answered after it is newer: its balance_after is the balance now.
    rollUnder50($auth)->assertCreated()->assertJsonPath('player_balance', '250.98');
    getJson('/play-api/balance', $auth)->assertJsonPath('balance', '250.98')->assertJsonPath('balance_check_id', null);
});

it('never asks a wallet that does not answer /wallet/balance', function (): void {
    walletAnswering();
    [, , $auth] = balanceOnDice(['wallet_balance' => '0']);

    getJson('/play-api/session', $auth)->assertOk()->assertJsonPath('balance', null);

    Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/balance'));
    expect(WalletBalanceCheck::count())->toBe(0);
});

it('starts from the last reported balance when the wallet does not answer the ask', function (Closure $answer): void {
    Http::fake(['wallet.test/w/wallet/balance' => $answer]);
    [$operator, $session, $auth] = balanceOnDice(['wallet_balance' => 'yes']);
    WalletTransaction::factory()->create([
        'operator_id' => $operator->id, 'player_id' => $session->player_id,
        'operator_callback_status' => WalletCallbackStatus::Completed,
        'operator_response' => ['status' => 200, 'body' => ['balance_after' => '3.00']],
    ])->forceFill(['answered_at' => now()->subHour()->format('Y-m-d H:i:s.u')])->save();

    getJson('/play-api/session', $auth)->assertOk()
        ->assertJsonPath('balance', '3.00')
        ->assertJsonPath('balance_before_launch', true);
})->with([
    'unavailable' => fn () => Http::response(['error' => 'down'], 503),
    'refused' => fn () => Http::response(['error' => 'player_not_found'], 404),
    'another currency' => fn () => Http::response(['balance' => '9.00', 'currency' => 'USD'], 200),
    'no balance' => fn () => Http::response(['ok' => true], 200),
]);

it('reads wallet_balance from the operator\'s flat config', function (mixed $value, bool $on): void {
    expect(Operator::factory()->make(['config' => ['wallet_balance' => $value]])->answersWalletBalance())->toBe($on);
})->with([
    ['1', true], ['true', true], [' Yes ', true], [true, true], [1, true],
    ['0', false], ['no', false], ['', false], [null, false], [false, false],
]);
