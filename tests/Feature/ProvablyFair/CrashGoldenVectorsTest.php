<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;

use function Pest\Laravel\postJson;

/*
 * Fixed crash vectors: every published crash point must keep verifying, so the maths may
 * never change. The values were cross-checked against an independent implementation of
 * the bustabit formula; do not regenerate them from the code under test.
 */
dataset('crash vectors', [
    'house seed, 1/33' => ['orange-golden-seed-0001', 'orange-house-seed-rotate-me', 1, 33, 'e03468f7285b30a96ddf4b181c2181f70cfc2416eca9e2341f4af03a990d2b17', 7.98],
    'house seed, 1/33 (2)' => ['orange-golden-seed-0002', 'orange-house-seed-rotate-me', 1, 33, 'a95d0fe9c4d46e4098daf544be65aaac346a74120f0a8032e05846742be647f0', 2.93],
    'hex seed, 1/100' => ['9f2c4e1ab0d34c7e8a5b6f1d2e3c4b5a6978877665544332211ffeeddccbbaa0', 'house', 7, 100, 'cef24c984c096bcd5544cca8d23a23dfba0650bad93ce5a709842a8df6b1ba68', 5.17],
    'player client seed, 1/20' => ['orange-golden-seed-0003', 'player-chosen', 42, 20, 'b967c017198f32c9d516d6c9d82f6aefd62edd9dceb130434cba8274b06bc0b7', 3.6],
    'instant crash, 1/33' => ['orange-golden-boundary-37', 'house', 1, 33, '2db161d4bd746c76d0c2e044f207019c411ceccdb1fe37eb42be768360ab59d5', 1.0],
    'big multiplier, 1/33' => ['orange-golden-big-160', 'house', 1, 33, 'fe2e1b586157c658e8b3961c41ad005418dd9feea43f863a4e556ba458b73eb5', 139.27],
]);

function goldenGame(int $divisor): Game
{
    $edge = round(1 / $divisor, 4);

    return Game::factory()->create(['house_edge' => $edge, 'rtp_target' => 1 - $edge]);
}

it('computes the published HMAC and crash point', function (string $seed, string $client, int $nonce, int $divisor, string $hmac, float $crash): void {
    $math = new ProvablyFairService;

    expect($math->hmac($seed, $client, $nonce))->toBe($hmac)
        ->and($math->crashPoint($seed, $client, $nonce, $divisor))->toBe($crash);
})->with('crash vectors');

it('resolves rounds and verifies through the crash engine with the game\'s house edge', function (string $seed, string $client, int $nonce, int $divisor, string $hmac, float $crash): void {
    $game = goldenGame($divisor);
    $round = GameRound::factory()->create([
        'game_id' => $game->id,
        'server_seed_id' => RngSeed::factory()->create(['server_seed' => $seed, 'server_seed_hash' => hash('sha256', $seed)])->id,
        'client_seed' => $client,
        'nonce' => $nonce,
    ]);
    $engine = app(CrashEngine::class);

    expect($engine->resolveRound($round))->toBe(['crash_point' => $crash])
        ->and($engine->verifier()->verify($game, $seed, $client, $nonce, []))->toMatchArray([
            'house_edge_divisor' => $divisor,
            'hmac' => $hmac,
            'crash_point' => $crash,
        ]);
})->with('crash vectors');

it('verifies through POST /v1/fairness/verify', function (string $seed, string $client, int $nonce, int $divisor, string $hmac, float $crash): void {
    postJson('/v1/fairness/verify', [
        'server_seed' => $seed,
        'client_seed' => $client,
        'nonce' => $nonce,
        'house_edge' => 1 / $divisor,
    ])->assertOk()
        ->assertJsonPath('house_edge_divisor', $divisor)
        ->assertJsonPath('hmac', $hmac)
        // JSON has no float type: an instant crash comes back as 1, not 1.0.
        ->assertJsonPath('crash_point', fn (int|float $value): bool => (float) $value === $crash);
})->with('crash vectors');
