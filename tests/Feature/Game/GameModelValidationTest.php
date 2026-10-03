<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;

it('rejects a game where rtp_target + house_edge != 1.0', function (): void {
    Game::factory()->create([
        'code' => 'bad-game',
        'rtp_target' => 0.97,
        'house_edge' => 0.10, // 1.07 — inconsistent
    ]);
})->throws(DomainException::class, 'rtp_target + house_edge must equal 1.0');

it('rejects an out-of-range rtp_target', function (): void {
    Game::factory()->create([
        'code' => 'too-high',
        'rtp_target' => 1.5,
        'house_edge' => -0.5,
    ]);
})->throws(DomainException::class);

it('accepts the canonical 0.97 / 0.03 split', function (): void {
    $game = Game::factory()->create([
        'code' => 'canonical',
        'rtp_target' => 0.97,
        'house_edge' => 0.03,
    ]);

    expect($game->rtp_target)->toBe(0.97);
    expect($game->house_edge)->toBe(0.03);
});

it('accepts the player-friendly 0.99 / 0.01 split', function (): void {
    $game = Game::factory()->create([
        'code' => 'friendly',
        'rtp_target' => 0.99,
        'house_edge' => 0.01,
    ]);

    expect($game->rtp_target)->toBe(0.99);
});

it('allows tiny float drift inside the tolerance', function (): void {
    // 0.97000001 + 0.02999999 = 1.00000000 — and even with 1e-7 float drift
    // both sides should still satisfy the |sum - 1.0| < 1e-4 invariant.
    $game = Game::factory()->create([
        'code' => 'drifty',
        'rtp_target' => 0.9700001,
        'house_edge' => 0.0299999,
    ]);

    expect($game)->not->toBeNull();
});
