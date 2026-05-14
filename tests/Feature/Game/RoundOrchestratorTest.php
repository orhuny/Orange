<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Exceptions\InvalidRoundTransition;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use App\Domain\ProvablyFair\Services\SeedRotator;

function orchestrator(): RoundOrchestrator
{
    $math = new ProvablyFairService;

    return new RoundOrchestrator(new SeedRotator($math), $math);
}

it('creates a waiting round bound to the active seed with an incrementing nonce', function (): void {
    $game = Game::factory()->create();
    $orch = orchestrator();

    $first = $orch->create($game);
    $second = $orch->create($game);

    expect($first->state)->toBe(RoundState::Waiting)
        ->and($first->round_uuid)->toHaveLength(36)
        ->and($first->server_seed_id)->toBe($second->server_seed_id)
        ->and($first->nonce)->toBe(1)
        ->and($second->nonce)->toBe(2);

    $seed = RngSeed::find($first->server_seed_id);
    expect($seed->used_round_count)->toBe(2);
});

it('drives a round through the full happy-path lifecycle', function (): void {
    $game = Game::factory()->create();
    $orch = orchestrator();

    $round = $orch->create($game);
    expect($round->state)->toBe(RoundState::Waiting);

    $round = $orch->openBetting($round);
    expect($round->state)->toBe(RoundState::Betting)
        ->and($round->betting_opened_at)->not->toBeNull();

    $round = $orch->start($round);
    expect($round->state)->toBe(RoundState::Running)
        ->and($round->started_at)->not->toBeNull()
        ->and($round->crash_point)->toBeGreaterThanOrEqual(1.00);

    $round = $orch->crash($round);
    expect($round->state)->toBe(RoundState::Crashed)
        ->and($round->crashed_at)->not->toBeNull();

    $round = $orch->settle($round);
    expect($round->state)->toBe(RoundState::Settled)
        ->and($round->settled_at)->not->toBeNull();
});

it('refuses an invalid transition (Betting -> Crashed)', function (): void {
    $game = Game::factory()->create();
    $orch = orchestrator();

    $round = $orch->openBetting($orch->create($game));

    expect(fn () => $orch->crash($round))->toThrow(InvalidRoundTransition::class);
});

it('crash_point matches independent ProvablyFairService computation', function (): void {
    $game = Game::factory()->create();
    $orch = orchestrator();

    $round = $orch->create($game);
    $round = $orch->start($orch->openBetting($round));

    $expected = (new ProvablyFairService)->crashPoint(
        $round->serverSeed->server_seed,
        $round->client_seed,
        $round->nonce,
    );

    expect($round->crash_point)->toBe($expected);
});

it('rotates the seed when the rotation threshold is hit on settle', function (): void {
    config()->set('provably_fair.seed_rotation_rounds', 1);
    $game = Game::factory()->create();
    $orch = orchestrator();

    $round = $orch->create($game);
    $originalSeedId = $round->server_seed_id;
    $round = $orch->settle($orch->crash($orch->start($orch->openBetting($round))));

    $newRound = $orch->create($game);
    expect($newRound->server_seed_id)->not->toBe($originalSeedId);

    $original = RngSeed::find($originalSeedId);
    expect($original->isActive())->toBeFalse();
});
