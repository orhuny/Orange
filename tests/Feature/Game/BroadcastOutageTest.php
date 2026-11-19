<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Exceptions;

use function Pest\Laravel\artisan;

require_once __DIR__.'/CashoutServiceTest.php';

// Live updates are best effort: with Reverb down (restarting, crashed, unreachable), rounds
// still run, settle and auto-cash-out; only the broadcasts are lost, and the failure is reported.
beforeEach(function (): void {
    config()->set('crash.growth_rate', 1000.0);
    app()->forgetInstance(MultiplierCalculator::class);

    // Every broadcast fails the way the Pusher client fails when Reverb refuses the connection.
    app(BroadcastManager::class)->extend('down', fn (): Broadcaster => new class extends Broadcaster
    {
        public function auth($request): mixed
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result): mixed
        {
            return null;
        }

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to 127.0.0.1 port 8080.');
        }
    });
    config()->set('broadcasting.connections.down', ['driver' => 'down']);
    config()->set('broadcasting.default', 'down');
    app(BroadcastManager::class)->forgetDrivers();

    Exceptions::fake();
});

it('still auto-cashes-out on a tick it cannot broadcast', function (): void {
    $bet = placedBetOnRunningRound(autoCashoutAt: 1.50);

    MultiplierTick::dispatch($bet->round, 1.51, 7.0);

    expect($bet->fresh()->status)->toBe(BetStatus::Won)
        ->and(Cashout::where('bet_id', $bet->id)->where('is_auto', true)->count())->toBe(1);
    Exceptions::assertReported(BroadcastException::class);
});

it('commits a crash and settles its losers when the crash cannot be broadcast', function (): void {
    $bet = placedBetOnRunningRound();

    app(RoundOrchestrator::class)->crash($bet->round->fresh());

    expect($bet->round->fresh()->state)->toBe(RoundState::Crashed)
        ->and($bet->fresh()->status)->toBe(BetStatus::Lost);
    Exceptions::assertReported(BroadcastException::class);
});

it('keeps crash:tick running while broadcasts fail', function (): void {
    placedBetOnRunningRound();

    artisan('crash:tick', ['--max-ticks' => 3, '--interval-ms' => 1])->assertSuccessful();

    Exceptions::assertReported(BroadcastException::class);
});

it('plays crash:loop rounds through to settled while broadcasts fail', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    $game = Game::factory()->create(['code' => 'crash']);

    artisan('crash:loop', ['--rounds' => 2, '--memory' => '-1'])->assertSuccessful();

    expect(GameRound::query()->where('game_id', $game->id)->pluck('state')->all())
        ->toBe([RoundState::Settled, RoundState::Settled]);
    Exceptions::assertReported(BroadcastException::class);
});
