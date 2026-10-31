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

it('keeps every crash-type game on the legacy crash channel unless narrowed in config', function (): void {
    $deepDive = GameRound::factory()->create(['game_id' => Game::factory()->create(['code' => 'crash', 'slug' => 'crash'])]);
    $skin = GameRound::factory()->create(['game_id' => Game::factory()->create(['code' => 'rocket', 'slug' => 'rocket'])]);

    expect(channelNames($deepDive))->toBe(["round.{$deepDive->round_uuid}", 'crash', 'game.crash'])
        ->and(channelNames($skin))->toBe(["round.{$skin->round_uuid}", 'crash', 'game.rocket']);

    config()->set('games.legacy_channel_games', ['crash']);
    expect(channelNames($deepDive->fresh()))->toContain('crash')
        ->and(channelNames($skin->fresh()))->not->toContain('crash');
});
