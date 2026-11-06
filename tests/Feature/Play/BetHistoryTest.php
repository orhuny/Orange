<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;

// GET /play-api/games/{code}/bets: the bet history a game page shows its player.

beforeEach(fn () => Cache::flush());

/** @return array{0: PlaySession, 1: string} */
function betHistorySession(Game $game, ?Operator $operator = null, string $playerId = 'p-1'): array
{
    $operator ??= Operator::factory()->create();
    OperatorGame::query()->firstOrCreate(['operator_id' => $operator->id, 'game_id' => $game->id], ['enabled' => true, 'settings' => ['play_mode' => 'direct']]);
    $token = app(LaunchService::class)->issue($operator, $game, $playerId, 'EUR')->token;

    return app(PlaySessionService::class)->exchange($token);
}

/** @param  array<string, mixed>  $attributes */
function historyBet(PlaySession $session, GameRound $round, array $attributes = []): Bet
{
    return Bet::factory()->create([
        'round_id' => $round->id,
        'game_id' => $round->game_id,
        'operator_id' => $session->operator_id,
        'player_id' => $session->player_id,
    ] + $attributes);
}

it('lists the player\'s bets on the game, newest first, with what each won', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    [$session, $bearer] = betHistorySession($game);
    $lostRound = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'crash_point' => 1.43]);
    $wonRound = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'crash_point' => 4.10]);
    historyBet($session, $lostRound, ['amount' => '5.00', 'status' => BetStatus::Lost, 'multiplier' => '0', 'payout_amount' => '0']);
    historyBet($session, $wonRound, ['amount' => '10.00', 'status' => BetStatus::Won, 'multiplier' => '2.5000', 'payout_amount' => '25.00']);

    getJson('/play-api/games/crash/bets', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonCount(2, 'bets')
        ->assertJsonPath('bets.0.status', 'won')
        ->assertJsonPath('bets.0.amount', '10.00000000')
        ->assertJsonPath('bets.0.currency', 'EUR')
        ->assertJsonPath('bets.0.multiplier', '2.5000')
        ->assertJsonPath('bets.0.payout_amount', '25.00000000')
        ->assertJsonPath('bets.0.round_id', $wonRound->id)
        ->assertJsonPath('bets.0.round_uuid', $wonRound->round_uuid)
        ->assertJsonPath('bets.0.crash_point', 4.1)
        ->assertJsonPath('bets.1.status', 'lost')
        ->assertJsonPath('bets.1.multiplier', null)
        ->assertJsonPath('bets.1.crash_point', 1.43);
});

it('never shows the live round\'s crash point', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    [$session, $bearer] = betHistorySession($game);
    $live = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Running, 'crash_point' => 87.65]);
    historyBet($session, $live);

    getJson('/play-api/games/crash/bets', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('bets.0.status', 'placed')
        ->assertJsonPath('bets.0.crash_point', null)
        ->assertDontSee('87.65');
});

it('leaves out bets that never counted, other players\' bets and other games', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $other = Game::factory()->create(['code' => 'crash-two', 'slug' => 'crash-two']);
    $operator = Operator::factory()->create();
    [$session, $bearer] = betHistorySession($game, $operator);
    [$someoneElse] = betHistorySession($game, $operator, 'p-2');
    $round = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'crash_point' => 2.00]);
    $kept = historyBet($session, $round, ['status' => BetStatus::Refunded]);
    foreach ([BetStatus::Rejected, BetStatus::RolledBack, BetStatus::Pending] as $status) {
        historyBet($session, $round, ['status' => $status]);
    }
    historyBet($someoneElse, $round, ['status' => BetStatus::Won]);
    historyBet($session, GameRound::factory()->create(['game_id' => $other->id, 'state' => RoundState::Settled]), ['status' => BetStatus::Won]);

    getJson('/play-api/games/crash/bets', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonCount(1, 'bets')
        ->assertJsonPath('bets.0.bet_id', $kept->id)
        ->assertJsonPath('bets.0.status', 'refunded');
});

it('only answers for the session\'s own game, and only with a session', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    Game::factory()->create(['code' => 'crash-two', 'slug' => 'crash-two']);
    [, $bearer] = betHistorySession($game);

    getJson('/play-api/games/crash-two/bets', ['Authorization' => "Bearer {$bearer}"])->assertNotFound()->assertJsonPath('error', 'unknown_game');
    getJson('/play-api/games/crash/bets')->assertUnauthorized();
});
