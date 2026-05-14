<?php

declare(strict_types=1);

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\Wallet\Models\Webhook;
use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['email' => 'admin@orange.local']));
});

function seedOneOfEach(): void
{
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);
    $game = Game::factory()->create();
    $seed = RngSeed::factory()->create();
    $round = GameRound::factory()->create([
        'game_id' => $game->id,
        'server_seed_id' => $seed->id,
    ]);
    $bet = Bet::factory()->create([
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'round_id' => $round->id,
    ]);
    Cashout::factory()->create(['bet_id' => $bet->id]);
    Webhook::factory()->create(['operator_id' => $operator->id]);
}

dataset('admin_resource_index_routes', [
    ['/admin/operators'],
    ['/admin/games'],
    ['/admin/game-rounds'],
    ['/admin/bets'],
    ['/admin/cashouts'],
    ['/admin/rng-seeds'],
    ['/admin/webhooks'],
    ['/admin/players'],
]);

it('admin resource index page renders for authenticated users', function (string $url): void {
    $this->get($url)->assertOk();
})->with('admin_resource_index_routes');

it('admin resource index renders with one row of seeded data', function (string $url): void {
    seedOneOfEach();
    $this->get($url)->assertOk();
})->with('admin_resource_index_routes');

it('admin dashboard renders for authenticated users', function (): void {
    $this->get('/admin')->assertOk();
});

it('admin login page renders for guests', function (): void {
    auth()->logout();
    $this->get('/admin/login')->assertOk();
});
