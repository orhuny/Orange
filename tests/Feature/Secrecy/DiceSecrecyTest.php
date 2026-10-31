<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Domain\Wallet\Models\Webhook;
use App\Filament\Resources\BetResource\Pages\ListBets;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\call;

// A dice outcome is decided by the player's server seed: it must stay secret until the
// player rotates the pair, everywhere a person or another system could see it.

const DICE_SECRET_SEED = 'dice-secrecy-server-seed-7c1e9a';

function secrecyCall(Operator $operator, string $method, string $path, array $body, string $key): string
{
    $json = (string) json_encode($body);
    $timestamp = time();

    return (string) call($method, $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, $method, $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json)->getContent();
}

it('keeps the server seed out of every response, callback, webhook, log and admin screen until rotation', function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
    Http::fake(['wallet.test/w/wallet/debit' => Http::sequence()
        ->push(['balance_after' => '9'], 200)->push(['balance_after' => '8'], 200)->push(['error' => 'insufficient_funds'], 402),
        'wallet.test/*' => Http::response(['ok' => true], 200)]);
    $logs = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logs): void {
        $logs[] = $e->message.' '.json_encode($e->context);
    });

    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create(['code' => 'dice', 'slug' => 'dice', 'type' => 'dice', 'access' => GameAccess::Listed,
        'rtp_target' => 0.99, 'house_edge' => 0.01, 'config' => ['min_chance' => '0.01', 'max_chance' => '98.00']]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR']);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create(['operator_id' => $operator->id, 'player_id' => $player->id, 'server_seed' => DICE_SECRET_SEED,
        'server_seed_hash' => hash('sha256', DICE_SECRET_SEED), 'client_seed' => 'c', 'nonce' => 0, 'active' => true]);

    $seen = [];
    foreach (['under', 'over', 'under'] as $i => $direction) {
        $seen[] = secrecyCall($operator, 'POST', '/v1/games/dice/bets', [
            'player_external_id' => 'p-1', 'amount' => '1.00', 'currency' => 'EUR', 'params' => ['target' => '50', 'direction' => $direction],
        ], "secret-{$i}");
    }
    $seen[] = secrecyCall($operator, 'GET', '/v1/players/p-1/seeds', [], 'seeds-look');
    $seen[] = (string) call('GET', '/v1/fairness/dice')->getContent();
    foreach (Http::recorded() as [$request]) {
        /** @var Request $request */
        $seen[] = $request->url().' '.$request->body();
    }
    $seen[] = Webhook::query()->get()->toJson();
    $seen[] = Bet::query()->with('playerSeed')->get()->toJson();
    $seen[] = PlayerSeed::query()->get()->toJson();
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $seen[] = Livewire::test(ListBets::class)->loadTable()->html();
    $seen = array_merge($seen, $logs);

    expect(Bet::query()->whereNotNull('outcome')->count())->toBe(2);
    foreach ($seen as $text) {
        expect($text)->not->toContain(DICE_SECRET_SEED);
    }

    // Revealed exactly when the player rotates the pair.
    expect(secrecyCall($operator, 'POST', '/v1/players/p-1/seeds/rotate', [], 'rotate'))->toContain(DICE_SECRET_SEED);
});
