<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\ProvablyFair\Models\RngSeed;
use Illuminate\Support\Str;

use function Pest\Laravel\getJson;

// GET /v1/rounds/{uuid}: a round's details, as the game page shows them.

it('shows a settled round\'s seeds and the result worked out from them', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'house_edge' => 0.03, 'rtp_target' => 0.97]);
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->settle($orchestrator->crash($orchestrator->start($orchestrator->openBetting($orchestrator->create($game)))));
    $seed = RngSeed::query()->findOrFail($round->server_seed_id);
    $hmac = hash_hmac('sha256', "{$round->client_seed}:{$round->nonce}", $seed->server_seed);

    getJson("/v1/rounds/{$round->round_uuid}")
        ->assertOk()
        ->assertJsonPath('round_id', $round->id)
        ->assertJsonPath('game', 'crash')
        ->assertJsonPath('state', 'settled')
        // A 1.00x round comes back as 1: compare the numbers.
        ->assertJsonPath('result.crash_point', fn ($cp): bool => (float) $cp === $round->crash_point)
        ->assertJsonPath('fairness.server_seed', $seed->server_seed)
        ->assertJsonPath('fairness.server_seed_hash', hash('sha256', $seed->server_seed))
        ->assertJsonPath('fairness.client_seed', $round->client_seed)
        ->assertJsonPath('fairness.nonce', $round->nonce)
        ->assertJsonPath('fairness.verification.hmac', $hmac)
        ->assertJsonPath('fairness.verification.hex', substr($hmac, 0, 13))
        ->assertJsonPath('fairness.verification.decimal', hexdec(substr($hmac, 0, 13)))
        ->assertJsonPath('fairness.verification.house_edge_divisor', 33)
        ->assertJsonPath('fairness.verification.crash_point', fn ($cp): bool => (float) $cp === $round->crash_point);
});

it('keeps a live round\'s seed and crash point secret', function (): void {
    $seed = RngSeed::factory()->create();
    $running = GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 87.65, 'server_seed_id' => $seed->id]);

    getJson("/v1/rounds/{$running->round_uuid}")
        ->assertOk()
        ->assertJsonPath('state', 'running')
        ->assertJsonPath('result', null)
        ->assertJsonPath('fairness.server_seed', null)
        ->assertJsonPath('fairness.server_seed_hash', $seed->server_seed_hash)
        ->assertJsonPath('fairness.verification', null)
        ->assertDontSee('87.65')
        ->assertDontSee($seed->server_seed);
});

it('shows the crash point once the round crashes, and the seed only once it settles', function (): void {
    $seed = RngSeed::factory()->rotated()->create();
    $crashed = GameRound::factory()->create(['state' => RoundState::Crashed, 'crash_point' => 3.21, 'server_seed_id' => $seed->id, 'started_at' => now()]);

    getJson("/v1/rounds/{$crashed->round_uuid}")
        ->assertOk()
        ->assertJsonPath('result.crash_point', 3.21)
        ->assertJsonPath('fairness.server_seed', null)
        ->assertJsonPath('fairness.verification', null)
        ->assertDontSee($seed->server_seed);
});

it('has nothing to verify for a round settled before it started', function (): void {
    $round = GameRound::factory()->create(['state' => RoundState::Settled, 'server_seed_id' => RngSeed::factory()->revealed()]);

    getJson("/v1/rounds/{$round->round_uuid}")
        ->assertOk()
        ->assertJsonPath('result.crash_point', null)
        ->assertJsonPath('fairness.verification', null);
});

it('does not find unknown rounds or rounds of draft games', function (): void {
    $draft = GameRound::factory()->create(['state' => RoundState::Settled, 'game_id' => Game::factory()->create(['status' => 'draft'])]);

    getJson('/v1/rounds/'.Str::uuid())->assertNotFound()->assertJsonPath('error', 'round_not_found');
    getJson("/v1/rounds/{$draft->round_uuid}")->assertNotFound();
    getJson('/v1/rounds/not-a-uuid')->assertNotFound();
});
