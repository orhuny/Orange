<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;

// Direct play: a bet counts only once the operator confirms its stake debit.

beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});
afterEach(fn () => Carbon::setTestNow());

/** @return array{0: Operator, 1: Game, 2: GameRound, 3: string} */
function directPlay(?Game $game = null, bool $lobby = false): array
{
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game ??= Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->openBetting($orchestrator->create($game));
    $token = app(LaunchService::class)->issue($operator, $lobby ? null : $game, 'p-1', 'EUR')->token;
    [, $bearer] = app(PlaySessionService::class)->exchange($token);

    return [$operator, $game, $round, $bearer];
}

/**
 * @param  array<string, mixed>  $body
 * @return TestResponse<Response>
 */
function directBet(string $bearer, array $body = ['amount' => '2.00'], string $code = 'crash', string $key = 'browser-key-0001'): TestResponse
{
    return postJson("/play-api/games/{$code}/bets", $body, ['Authorization' => "Bearer {$bearer}", 'Idempotency-Key' => $key]);
}

it('places the bet once the operator accepts the stake debit', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '98.00'], 200)]);
    [, , $round, $bearer] = directPlay();

    directBet($bearer, ['amount' => '2.00', 'params' => ['auto_cashout_at' => '2.00']])
        ->assertCreated()
        ->assertJsonPath('status', 'placed')
        ->assertJsonPath('round_uuid', $round->round_uuid)
        ->assertJsonPath('auto_cashout_at', '2.00')
        ->assertJsonPath('player_balance', '98.00');

    $bet = Bet::sole();
    expect($bet->status)->toBe(BetStatus::Placed)
        ->and($bet->idempotency_key)->toBe('ps-browser-key-0001')
        ->and((string) $round->fresh()->total_bet_amount)->toBe('2.00000000');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/debit') && $r['tx_id'] === 'bet:ps-browser-key-0001');
});

it('rejects the bet when the operator refuses the stake, and takes nothing', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['error' => 'insufficient_funds'], 402)]);
    [, , $round, $bearer] = directPlay();

    directBet($bearer)
        ->assertStatus(402)
        ->assertJsonPath('error', 'wallet_rejected')
        ->assertJsonPath('reason', 'insufficient_funds');

    expect(Bet::sole()->status)->toBe(BetStatus::Rejected)
        ->and((string) $round->fresh()->total_bet_amount)->toBe('0.00000000');
    Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/rollback'));
});

it('rolls the bet back when the operator does not answer', function (): void {
    Http::fake([
        'wallet.test/w/wallet/debit' => Http::response(['error' => 'down'], 503),
        'wallet.test/*' => Http::response(['ok' => true], 200),
    ]);
    [, , $round, $bearer] = directPlay();

    directBet($bearer)->assertStatus(503)->assertJsonPath('error', 'wallet_unavailable');

    $bet = Bet::sole();
    expect($bet->status)->toBe(BetStatus::RolledBack)
        ->and((string) $round->fresh()->total_bet_amount)->toBe('0.00000000');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/rollback') && $r['tx_id'] === "rollback:{$bet->id}");
});

it('counts a stake confirmed after its round crashed as a lost bet', function (): void {
    [, , $round, $bearer] = directPlay();
    // The round starts and crashes while the operator is still answering the debit.
    Http::fake(['wallet.test/*' => function () use ($round) {
        $orchestrator = app(RoundOrchestrator::class);
        $orchestrator->crash($orchestrator->start($round->fresh()));

        return Http::response(['balance_after' => '98.00'], 200);
    }]);

    directBet($bearer)->assertCreated();

    $bet = Bet::sole();
    expect($bet->status)->toBe(BetStatus::Lost)
        ->and((string) $bet->payout_amount)->toBe('0.00000000');
});

it('never cashes out a bet whose stake is still pending', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '98.00'], 200)]);
    [, , $round, $bearer] = directPlay();
    directBet($bearer)->assertCreated();
    $bet = Bet::sole();
    $bet->forceFill(['status' => BetStatus::Pending])->save();
    app(RoundOrchestrator::class)->start($round->fresh());

    postJson("/play-api/bets/{$bet->id}/cashout", [], ['Authorization' => "Bearer {$bearer}"])->assertStatus(409);

    expect($bet->fresh()->status)->toBe(BetStatus::Pending);
});

it('cashes out the player\'s own bet, and nobody else\'s', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '98.00'], 200)]);
    [, , $round, $bearer] = directPlay();
    $betId = directBet($bearer)->json('bet_id');
    Carbon::setTestNow(now());
    app(RoundOrchestrator::class)->start($round->fresh());
    Carbon::setTestNow(now()->addSeconds(5));
    [, , , $otherBearer] = directPlay(Game::query()->where('code', 'crash')->sole());

    postJson("/play-api/bets/{$betId}/cashout", [], ['Authorization' => "Bearer {$otherBearer}"])->assertNotFound();
    postJson("/play-api/bets/{$betId}/cashout", [], ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('bet_id', $betId);
    expect(Bet::query()->find($betId)?->status)->toBe(BetStatus::Won);
});

it('takes one stake for a repeated request with the same key', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '98.00'], 200)]);
    [, , , $bearer] = directPlay();

    directBet($bearer)->assertCreated();
    directBet($bearer)->assertCreated();

    expect(Bet::count())->toBe(1)
        ->and(WalletTransaction::query()->where('type', 'debit')->count())->toBe(1);
});

it('lets a session bet only on its own game, or on any enabled game from the lobby', function (): void {
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '98.00'], 200)]);
    [$operator, , , $bearer] = directPlay();
    $other = Game::factory()->create(['code' => 'other', 'slug' => 'other']);
    app(RoundOrchestrator::class)->openBetting(app(RoundOrchestrator::class)->create($other));

    directBet($bearer, code: 'other')->assertNotFound()->assertJsonPath('error', 'unknown_game');

    $listed = Game::factory()->create(['code' => 'listed', 'slug' => 'listed', 'access' => GameAccess::Listed]);
    [, , , $lobbyBearer] = directPlay($listed, lobby: true);
    directBet($lobbyBearer, code: 'listed')->assertStatus(403)->assertJsonPath('error', 'game_not_enabled');
    directBet($lobbyBearer, code: 'other', key: 'browser-key-0002')->assertCreated();
});

it('requires an Idempotency-Key from the page', function (): void {
    [, , , $bearer] = directPlay();

    postJson('/play-api/games/crash/bets', ['amount' => '1.00'], ['Authorization' => "Bearer {$bearer}"])
        ->assertStatus(400)->assertJsonPath('error', 'missing_idempotency_key');
});
