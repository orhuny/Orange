<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('describes how to verify a game, including the public house client seed', function (): void {
    config()->set('provably_fair.house_client_seed', 'published-house-seed');
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'house_edge' => 0.03, 'rtp_target' => 0.97]);

    getJson('/v1/fairness/crash')
        ->assertOk()
        ->assertJsonPath('game', 'crash')
        ->assertJsonPath('house_client_seed', 'published-house-seed')
        ->assertJsonPath('house_edge', '0.0300')
        ->assertJsonPath('house_edge_divisor', 33)
        ->assertJsonPath('verify_url', url('/v1/fairness/crash/verify'));
});

it('verifies a crash round from its revealed seed with the game\'s house edge', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'house_edge' => 0.03, 'rtp_target' => 0.97]);
    $seed = 'orange-golden-seed-0001';

    // A golden vector (see CrashGoldenVectorsTest): crash point 7.98 at 1/33.
    get('/v1/fairness/crash/verify?'.http_build_query([
        'server_seed' => $seed,
        'client_seed' => 'orange-house-seed-rotate-me',
        'nonce' => 1,
        'expected_server_seed_hash' => hash('sha256', $seed),
    ]))->assertOk()
        ->assertJsonPath('game', 'crash')
        ->assertJsonPath('hmac', 'e03468f7285b30a96ddf4b181c2181f70cfc2416eca9e2341f4af03a990d2b17')
        ->assertJsonPath('hex', 'e03468f7285b3')
        ->assertJsonPath('decimal', 3944251263190451)
        ->assertJsonPath('instant_crash', false)
        ->assertJsonPath('crash_point', 7.98)
        ->assertJsonPath('expected_hash_matches', true);
});

it('keeps past outcomes verifiable after a game is disabled, but hides drafts and unknown games', function (): void {
    Game::factory()->create(['code' => 'retired', 'slug' => 'retired', 'status' => 'disabled']);
    Game::factory()->create(['code' => 'secret', 'slug' => 'secret', 'status' => 'draft']);

    getJson('/v1/fairness/retired')->assertOk();
    getJson('/v1/fairness/secret')->assertNotFound()->assertJsonPath('error', 'unknown_game');
    getJson('/v1/fairness/nope/verify?server_seed=abcdefgh&client_seed=c&nonce=1')->assertNotFound();
});

it('answers an unusable house edge with a 422, on the new and the legacy endpoint (D6)', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $seeds = ['server_seed' => 'orange-golden-seed-0001', 'client_seed' => 'c', 'nonce' => 1];

    getJson('/v1/fairness/crash/verify?'.http_build_query($seeds + ['house_edge' => 0.7]))
        ->assertStatus(422)->assertJsonValidationErrors('house_edge');
    postJson('/v1/fairness/verify', $seeds + ['house_edge' => 0.7])
        ->assertStatus(422)->assertJsonValidationErrors('house_edge');
    // Edges that always worked still do.
    postJson('/v1/fairness/verify', $seeds + ['house_edge' => 0.66])->assertOk()->assertJsonPath('house_edge_divisor', 2);
});
