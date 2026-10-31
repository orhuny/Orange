<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    // Steep growth so the running phase lasts milliseconds whatever the crash point.
    config()->set('crash.growth_rate', 1000.0);
    app()->forgetInstance(MultiplierCalculator::class);
});

it('plays a round on the engine\'s schedule from config', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    $game = Game::factory()->create(['code' => 'crash']);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('betting=0s waiting=0s')
        ->assertSuccessful();

    $round = GameRound::query()->where('game_id', $game->id)->sole();
    $math = new ProvablyFairService;
    expect($round->state)->toBe(RoundState::Settled)
        ->and($round->crash_point)->toBe($math->crashPoint($round->serverSeed->server_seed, $round->client_seed, $round->nonce, $math->divisorForGame($game)))
        ->and($round->serverSeed->isRevealed())->toBeTrue();
});

it('lets --betting and --waiting override the schedule', function (): void {
    config()->set('crash.betting_seconds', 30);
    config()->set('crash.waiting_seconds', 30);
    Game::factory()->create(['code' => 'crash']);

    artisan('crash:loop', ['--rounds' => 1, '--betting' => 0, '--waiting' => 0, '--memory' => '-1'])
        ->expectsOutputToContain('betting=0s waiting=0s')
        ->assertSuccessful();
});

it('settles orphans and retires the seeds of finished rounds at start-up', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    $game = Game::factory()->create(['code' => 'crash']);
    // A seed shared across rounds, as before seeds became per-round, and a round a killed loop left running.
    $shared = RngSeed::factory()->create();
    GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'server_seed_id' => $shared->id]);
    $orphan = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Running, 'server_seed_id' => $shared->id, 'crash_point' => 5.0, 'started_at' => now()]);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('cleaned up 1 orphan round(s)')
        ->expectsOutputToContain('retired 1 seed(s)')
        ->assertSuccessful();

    expect($orphan->fresh()->state)->toBe(RoundState::Settled)
        ->and($shared->fresh()->isActive())->toBeFalse()
        ->and($shared->fresh()->isRevealed())->toBeTrue();
});
