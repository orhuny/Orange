<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\BetPlaced;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundBetsUpdated;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\CashoutService;
use App\Domain\Game\Services\LiveBets;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;

// The live bet list every player sees: round.bets broadcasts and GET /v1/rounds/{uuid}/bets.

beforeEach(fn () => Http::fake());

function liveRound(): GameRound
{
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->start($orchestrator->openBetting($orchestrator->create(Game::factory()->create())));
    $round->forceFill(['crash_point' => 100.00, 'started_at' => Carbon::now()->subSeconds(2)])->save();

    return $round;
}

/** @param  array<string, mixed>  $attributes */
function liveBet(GameRound $round, string $playerId = 'player-123456', array $attributes = []): Bet
{
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => $playerId]);

    return Bet::factory()->create(array_merge([
        'round_id' => $round->id,
        'game_id' => $round->game_id,
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'amount' => '10.00000000',
        'currency' => 'EUR',
        'status' => BetStatus::Placed,
    ], $attributes));
}

/** @return list<array<string, mixed>> every row the round.bets messages carried */
function broadcastRows(): array
{
    $rows = [];
    foreach (Event::dispatched(RoundBetsUpdated::class) as [$event]) {
        array_push($rows, ...$event->bets);
    }

    return $rows;
}

it('broadcasts a bet that counts, with its player masked', function (): void {
    $round = liveRound();
    $bet = liveBet($round, '9876543');
    Event::fake([RoundBetsUpdated::class]);

    BetPlaced::dispatch($bet);

    Event::assertDispatched(RoundBetsUpdated::class, fn (RoundBetsUpdated $e): bool => $e->broadcastAs() === 'round.bets'
        && in_array('crash', collect($e->broadcastOn())->pluck('name')->all(), true)
        && $e->broadcastWith() === ['round_uuid' => $round->round_uuid, 'bets' => [[
            'id' => $bet->id, 'player' => '9***3', 'amount' => '10.00000000', 'currency' => 'EUR', 'multiplier' => null, 'payout' => null,
        ]]]);
});

it('does not broadcast a bet that does not count', function (): void {
    $round = liveRound();
    Event::fake([RoundBetsUpdated::class]);

    // A direct-play stake confirmed after its round ended, and one still being debited.
    BetPlaced::dispatch(liveBet($round, 'p-1', ['status' => BetStatus::Lost]));
    BetPlaced::dispatch(liveBet($round, 'p-2', ['status' => BetStatus::Pending]));

    Event::assertNotDispatched(RoundBetsUpdated::class);
});

it('broadcasts a cash-out with its multiplier and win', function (): void {
    $bet = liveBet(liveRound());
    Event::fake([RoundBetsUpdated::class]);

    app(CashoutService::class)->cashout($bet);

    $row = broadcastRows()[0];
    expect($row['id'])->toBe($bet->id)
        ->and($row['multiplier'])->toBe((string) $bet->fresh()?->multiplier)
        ->and($row['payout'])->toBe((string) $bet->fresh()?->payout_amount)
        ->and($row['multiplier'])->not->toBeNull();
});

it('sends a tick\'s auto-cashouts together, at most 30 rows a message', function (): void {
    $round = liveRound();
    $bets = collect(range(1, 35))->map(fn (int $i): Bet => liveBet($round, "player-{$i}", ['auto_cashout_at' => '1.50']));
    Event::fake([RoundBetsUpdated::class]);

    MultiplierTick::dispatch($round, 1.51, 7.0);

    Event::assertDispatchedTimes(RoundBetsUpdated::class, 2);
    expect(collect(broadcastRows())->pluck('id')->sort()->values()->all())->toBe($bets->pluck('id')->sort()->values()->all());
});

it('does not broadcast a bet whose transaction rolls back', function (): void {
    $bet = liveBet(liveRound());
    Event::fake([RoundBetsUpdated::class]);

    try {
        DB::transaction(function () use ($bet): void {
            app(LiveBets::class)->publish($bet);
            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(RoundBetsUpdated::class);
});

it('keeps a cash-out working when the live list cannot be broadcast', function (): void {
    $bet = liveBet(liveRound());
    Event::listen(RoundBetsUpdated::class, fn () => throw new RuntimeException('Reverb is down'));

    app(CashoutService::class)->cashout($bet);

    expect($bet->fresh()?->getAttribute('status'))->toBe(BetStatus::Won);
});

it('lists the round\'s bets that count, biggest stake first, and nobody\'s auto-cashout', function (): void {
    $round = liveRound();
    liveBet($round, 'alice', ['amount' => '2.00']);
    $won = liveBet($round, 'bob-77', ['amount' => '50.00', 'status' => BetStatus::Won, 'multiplier' => '2.5000', 'payout_amount' => '125.00', 'auto_cashout_at' => '2.50']);
    foreach ([BetStatus::Pending, BetStatus::Rejected, BetStatus::RolledBack] as $i => $status) {
        liveBet($round, "never-{$i}", ['amount' => '99.00', 'status' => $status]);
    }

    getJson("/v1/rounds/{$round->round_uuid}/bets")
        ->assertOk()
        ->assertJsonPath('round_uuid', $round->round_uuid)
        ->assertJsonPath('state', 'running')
        ->assertJsonCount(2, 'bets')
        ->assertJsonPath('bets.0.id', $won->id)
        ->assertJsonPath('bets.0.player', 'b***7')
        ->assertJsonPath('bets.0.multiplier', '2.5000')
        ->assertJsonPath('bets.0.payout', '125.00000000')
        ->assertJsonPath('bets.1.player', 'a***e')
        ->assertJsonPath('bets.1.multiplier', null)
        ->assertDontSee('auto_cashout')
        ->assertDontSee('alice');
});

it('can be switched off', function (): void {
    config()->set('crash.live_bets', false);
    $round = liveRound();
    $bet = liveBet($round);
    Event::fake([RoundBetsUpdated::class]);

    BetPlaced::dispatch($bet);

    Event::assertNotDispatched(RoundBetsUpdated::class);
    getJson("/v1/rounds/{$round->round_uuid}/bets")->assertNotFound();
});

it('masks players to their first and last character', function (string $id, string $masked): void {
    expect(LiveBets::mask($id))->toBe($masked);
})->with([
    ['123456', '1***6'],
    ['ab', 'a***'],
    ['  ', '***'],
    ['çağrı', 'ç***ı'],
]);
