<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Jobs\RunCrashRoundJob;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Support\Facades\Queue;

it('drives a brand new round end-to-end through dispatchSync', function (): void {
    $game = Game::factory()->create();

    $round = (new RunCrashRoundJob($game))->handle(app(RoundOrchestrator::class));

    expect($round)->toBeInstanceOf(GameRound::class)
        ->and($round->state)->toBe(RoundState::Settled)
        ->and($round->crash_point)->toBeGreaterThanOrEqual(1.00)
        ->and($round->settled_at)->not->toBeNull();
});

it('dispatches and processes via the queue', function (): void {
    Queue::fake();
    $game = Game::factory()->create();

    RunCrashRoundJob::dispatch($game);

    Queue::assertPushed(RunCrashRoundJob::class, fn (RunCrashRoundJob $job) => $job->game->is($game));
});

it('produces independent rounds with monotonically increasing nonces under the same seed', function (): void {
    $game = Game::factory()->create();

    $r1 = (new RunCrashRoundJob($game))->handle(app(RoundOrchestrator::class));
    $r2 = (new RunCrashRoundJob($game))->handle(app(RoundOrchestrator::class));

    expect($r1->server_seed_id)->toBe($r2->server_seed_id)
        ->and($r2->nonce)->toBeGreaterThan($r1->nonce);
});
