<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Models\Webhook;
use App\Filament\Resources\GameRoundResource\Pages\ListGameRounds;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// crash_point is stored when the round starts; it must stay secret until the crash.

it('does not show the live round\'s crash point in the admin panel', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $running = GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 87.65]);
    $settled = GameRound::factory()->create(['state' => RoundState::Settled, 'crash_point' => 3.21]);

    Livewire::test(ListGameRounds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$running, $settled])
        ->assertTableColumnStateSet('crash_point', null, $running)
        ->assertTableColumnStateSet('crash_point', 3.21, $settled)
        ->assertDontSee('87.65');
});

it('does not put the live round\'s crash point in the game\'s recent crashes', function (): void {
    GameRound::factory()->create(['state' => RoundState::Settled, 'crash_point' => 3.21]);
    GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 87.65]);

    get('/crash')->assertOk()->assertSee('3.21')->assertDontSee('87.65');
});

// Each round's server seed is revealed when it settles; it must never decide another round.

it('never lets a revealed seed decide a later round', function (): void {
    $orchestrator = app(RoundOrchestrator::class);
    $game = Game::factory()->create();
    $published = null;
    Event::listen(RoundSettled::class, function (RoundSettled $event) use (&$published): void {
        $published = $event->broadcastWith()['server_seed'];
    });

    $first = $orchestrator->create($game);
    $orchestrator->settle($orchestrator->crash($orchestrator->start($orchestrator->openBetting($first))));
    $next = $orchestrator->create($game);

    expect($published)->toBeString()
        ->and(hash('sha256', $published))->toBe($first->serverSeed->server_seed_hash)
        ->and($next->serverSeed->server_seed)->not->toBe($published)
        ->and(GameRound::query()->where('server_seed_id', $first->server_seed_id)->count())->toBe(1);
});

it('does not broadcast or webhook a seed that a live round still uses', function (): void {
    Http::fake();
    // A seed shared across rounds, as before seeds became per-round.
    $seed = RngSeed::factory()->create();
    GameRound::factory()->create(['state' => RoundState::Running, 'server_seed_id' => $seed->id, 'crash_point' => 87.65]);
    $finished = GameRound::factory()->create(['state' => RoundState::Crashed, 'server_seed_id' => $seed->id, 'crash_point' => 3.21]);
    Bet::factory()->create(['round_id' => $finished->id]);
    $published = 'not dispatched';
    Event::listen(RoundSettled::class, function (RoundSettled $event) use (&$published): void {
        $published = $event->broadcastWith()['server_seed'];
    });

    app(RoundOrchestrator::class)->settle($finished);

    $webhook = Webhook::query()->where('event', WebhookEvent::RoundSettled)->sole();
    expect($published)->toBeNull()
        ->and(data_get($webhook->payload, 'server_seed', 'missing'))->toBeNull()
        ->and($seed->fresh()->isRevealed())->toBeFalse();
});
