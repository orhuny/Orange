<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Events\RoundChannels;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\artisan;

/** @return list<string> */
function channelNames(GameRound $round): array
{
    return array_map(fn (Channel $c): string => $c->name, RoundChannels::for($round));
}

it('ticks every running round of every game, each on its own game channel', function (): void {
    Event::fake([MultiplierTick::class]);
    $deepDive = GameRound::factory()->create([
        'game_id' => Game::factory()->create(['code' => 'crash', 'slug' => 'crash']),
        'state' => RoundState::Running, 'started_at' => now()->subSeconds(3), 'crash_point' => 50.0,
    ]);
    $skin = GameRound::factory()->create([
        'game_id' => Game::factory()->create(['code' => 'rocket', 'slug' => 'rocket']),
        'state' => RoundState::Running, 'started_at' => now()->subSeconds(1), 'crash_point' => 50.0,
    ]);
    $stale = GameRound::factory()->create(['state' => RoundState::Running, 'started_at' => now()->subMinutes(5), 'crash_point' => 50.0]);
    GameRound::factory()->create(['state' => RoundState::Betting]);

    artisan('crash:tick', ['--once' => true])->assertSuccessful();

    Event::assertDispatchedTimes(MultiplierTick::class, 2);
    foreach ([$deepDive, $skin] as $round) {
        Event::assertDispatched(MultiplierTick::class, fn (MultiplierTick $e): bool => $e->round->is($round)
            && $e->multiplier >= 1.0
            && in_array("game.{$round->game->code}", channelNames($e->round), true));
    }
    Event::assertNotDispatched(MultiplierTick::class, fn (MultiplierTick $e): bool => $e->round->is($stale));
});

it('keeps only the original game on the legacy crash channel, unless config widens it', function (): void {
    $deepDive = GameRound::factory()->create(['game_id' => Game::factory()->create(['code' => 'crash', 'slug' => 'crash'])]);
    $luckyDuck = GameRound::factory()->create(['game_id' => Game::factory()->create(['code' => 'lucky-duck', 'slug' => 'lucky-duck'])]);

    // afq's /crash page listens on `crash` and shows one game; Orange's page listens on game.{code}.
    expect(channelNames($deepDive))->toBe(["round.{$deepDive->round_uuid}", 'crash', 'game.crash'])
        ->and(channelNames($luckyDuck))->toBe(["round.{$luckyDuck->round_uuid}", 'game.lucky-duck']);

    config()->set('games.legacy_channel_games', null);
    expect(channelNames($deepDive->fresh()))->toContain('crash')
        ->and(channelNames($luckyDuck->fresh()))->toContain('crash');
});
