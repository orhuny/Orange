<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\OutcomeNotPublic;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\postJson;

// CrashEngine is an adapter over the existing crash maths: everything here must match it exactly.

function crashEngine(): CrashEngine
{
    return app(CrashEngine::class);
}

it('is the round-based crash engine', function (): void {
    expect(CrashEngine::type())->toBe('crash')
        ->and(crashEngine()->family())->toBe(EngineFamily::RoundBased)
        ->and(crashEngine()->maxMultiplier(Game::factory()->create()))->toBe('0');
});

it('resolves the same crash point as ProvablyFairService for the game\'s house edge', function (float $houseEdge): void {
    $math = new ProvablyFairService;
    $game = Game::factory()->create(['house_edge' => $houseEdge, 'rtp_target' => 1 - $houseEdge]);

    foreach (range(1, 50) as $nonce) {
        $round = GameRound::factory()->create([
            'game_id' => $game->id,
            'server_seed_id' => RngSeed::factory(),
            'client_seed' => 'house-'.$nonce,
            'nonce' => $nonce,
        ]);

        expect(crashEngine()->resolveRound($round))->toBe([
            'crash_point' => $math->crashPoint($round->serverSeed->server_seed, $round->client_seed, $nonce, $math->divisorForGame($game)),
        ]);
    }
})->with([0.03, 0.01]);

it('verifies exactly like POST /v1/fairness/verify, using the game house edge by default', function (?float $houseEdge): void {
    // 0.01 (divisor 100) differs from the config default (33), so a fallback to it would show.
    $game = Game::factory()->create(['house_edge' => 0.01, 'rtp_target' => 0.99]);
    $params = $houseEdge === null ? [] : ['house_edge' => $houseEdge];

    $legacy = postJson('/v1/fairness/verify', [
        'server_seed' => 'a-revealed-server-seed',
        'client_seed' => 'house-client-seed',
        'nonce' => 7,
        'house_edge' => $houseEdge ?? 0.01,
    ])->assertOk()->json();

    $verified = crashEngine()->verifier()->verify($game, 'a-revealed-server-seed', 'house-client-seed', 7, $params);

    expect($verified)->toBe([
        'algorithm' => $legacy['algorithm'],
        'house_edge_divisor' => $legacy['house_edge_divisor'],
        'server_seed_hash' => $legacy['server_seed_hash'],
        'hmac' => $legacy['hmac'],
        'crash_point' => (float) $legacy['crash_point'],
    ])->and($verified['house_edge_divisor'])->toBe($houseEdge === null ? 100 : 20);
})->with([null, 0.05]);

it('accepts only house edges the verifier can compute with', function (): void {
    $rules = ['house_edge' => crashEngine()->verifier()->rules()['house_edge']];
    $game = Game::factory()->create();

    // 0.66 → round(1.52) = 2 is the largest usable edge; 0.67 → round(1.49) = 1 is not.
    foreach ([0.0000001, 0.03, 0.6, 0.66] as $edge) {
        expect(validator(['house_edge' => $edge], $rules)->passes())->toBeTrue();
        crashEngine()->verifier()->verify($game, 'seed', 'client', 1, ['house_edge' => $edge]);
    }
    foreach ([0, 0.67, 0.99, 1, 1e-20, 'abc'] as $edge) {
        expect(validator(['house_edge' => $edge], $rules)->passes())->toBeFalse();
    }
});

it('keeps the outcome secret until the round has crashed', function (RoundState $state, bool $public): void {
    $round = GameRound::factory()->create(['state' => $state, 'crash_point' => 4.56]);

    if ($public) {
        expect(crashEngine()->publicResult($round))->toBe(['crash_point' => 4.56]);
    } else {
        expect(fn () => crashEngine()->publicResult($round))->toThrow(OutcomeNotPublic::class);
    }
})->with([
    [RoundState::Waiting, false],
    [RoundState::Betting, false],
    [RoundState::Running, false],
    [RoundState::Crashed, true],
    [RoundState::Settled, true],
]);

it('has no crash point to show for a round force-settled before it started', function (): void {
    $round = GameRound::factory()->create(['state' => RoundState::Settled, 'crash_point' => null]);

    expect(crashEngine()->publicResult($round))->toBe(['crash_point' => null]);
});

it('ticks with the same payload as MultiplierTick and never the crash point', function (): void {
    $now = Carbon::parse('2026-11-01 12:00:05');
    $round = GameRound::factory()->create([
        'state' => RoundState::Running,
        'started_at' => $now->copy()->subSeconds(5),
        'crash_point' => 87.65,
    ]);
    $multiplier = app(MultiplierCalculator::class)->forRound($round, $now);

    $tick = crashEngine()->publicTick($round, $now);

    expect($tick)->toBe((new MultiplierTick($round, $multiplier, 5.0))->broadcastWith())
        ->and($tick)->not->toHaveKey('crash_point')
        ->and(json_encode($tick))->not->toContain('87.65');
});

it('takes its schedule from config/crash.php', function (): void {
    config()->set('crash.waiting_seconds', 4);
    config()->set('crash.betting_seconds', 9);
    config()->set('crash.tick_interval_ms', 150);

    $schedule = crashEngine()->schedule(Game::factory()->create());

    expect([$schedule->waitingSeconds, $schedule->bettingSeconds, $schedule->tickIntervalMs])->toBe([4, 9, 150]);
});

it('runs for as long as the multiplier takes to reach the crash point', function (): void {
    $round = GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 2.5]);

    expect(crashEngine()->runningDuration($round))
        ->toBe(app(MultiplierCalculator::class)->durationToReach(2.5));
});

it('validates auto_cashout_at like POST /v1/bets', function (): void {
    $game = Game::factory()->create();

    expect(crashEngine()->validateBetParams($game, []))->toBe(['auto_cashout_at' => null])
        ->and(crashEngine()->validateBetParams($game, ['auto_cashout_at' => '2.00']))->toBe(['auto_cashout_at' => '2.00'])
        ->and(fn () => crashEngine()->validateBetParams($game, ['auto_cashout_at' => '1.00']))->toThrow(InvalidBetParams::class);
});

it('drives RoundOrchestrator: a started round gets the crash point the original maths computes', function (): void {
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->openBetting($orchestrator->create(Game::factory()->create()));

    $started = $orchestrator->start($round);

    $math = new ProvablyFairService;
    expect($started->crash_point)->toBe($math->crashPoint(
        $started->serverSeed->server_seed,
        $started->client_seed,
        $started->nonce,
        $math->divisorForGame($started->game),
    ));
});
