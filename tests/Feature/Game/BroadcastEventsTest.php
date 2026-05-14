<?php

declare(strict_types=1);

use App\Domain\Game\Events\BettingOpened;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundCrashed;
use App\Domain\Game\Events\RoundSettled;
use App\Domain\Game\Events\RoundStarted;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Support\Facades\Event;

function freshRound(): GameRound
{
    $game = Game::factory()->create();
    $orchestrator = app(RoundOrchestrator::class);

    return $orchestrator->create($game);
}

it('dispatches BettingOpened with correct channels and payload', function (): void {
    Event::fake([BettingOpened::class]);

    $round = freshRound();
    app(RoundOrchestrator::class)->openBetting($round);

    Event::assertDispatched(BettingOpened::class, function (BettingOpened $event) use ($round): bool {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
        $payload = $event->broadcastWith();

        return in_array("round.{$round->round_uuid}", $channels, true)
            && in_array('crash', $channels, true)
            && $payload['round_uuid'] === $round->round_uuid
            && $event->broadcastAs() === 'round.betting_opened';
    });
});

it('dispatches RoundStarted -> RoundCrashed -> RoundSettled in order', function (): void {
    Event::fake([RoundStarted::class, RoundCrashed::class, RoundSettled::class]);

    $orchestrator = app(RoundOrchestrator::class);
    $round = freshRound();
    $round = $orchestrator->openBetting($round);
    $round = $orchestrator->start($round);
    $round = $orchestrator->crash($round);
    $orchestrator->settle($round);

    Event::assertDispatched(RoundStarted::class);
    Event::assertDispatched(RoundCrashed::class);
    Event::assertDispatched(RoundSettled::class);
});

it('RoundSettled payload exposes the revealed server seed', function (): void {
    Event::fake([RoundSettled::class]);

    $orchestrator = app(RoundOrchestrator::class);
    $round = freshRound();
    $round = $orchestrator->openBetting($round);
    $round = $orchestrator->start($round);
    $round = $orchestrator->crash($round);
    $orchestrator->settle($round);

    Event::assertDispatched(RoundSettled::class, function (RoundSettled $event): bool {
        $payload = $event->broadcastWith();

        return is_string($payload['server_seed'])
            && strlen($payload['server_seed']) >= 32
            && $payload['crash_point'] >= 1.0;
    });
});

it('MultiplierTick payload carries multiplier and elapsed seconds', function (): void {
    Event::fake([MultiplierTick::class]);

    $orchestrator = app(RoundOrchestrator::class);
    $round = freshRound();
    $round = $orchestrator->openBetting($round);
    $round = $orchestrator->start($round);

    MultiplierTick::dispatch($round, 1.42, 5.5);

    Event::assertDispatched(MultiplierTick::class, function (MultiplierTick $event): bool {
        $payload = $event->broadcastWith();

        return $payload['multiplier'] === 1.42
            && $payload['elapsed_seconds'] === 5.5
            && $event->broadcastAs() === 'round.tick';
    });
});
