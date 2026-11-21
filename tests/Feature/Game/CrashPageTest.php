<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;

use function Pest\Laravel\get;

it('offers the rules, with the RTP of the game being played', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'type' => 'crash', 'rtp_target' => 0.97, 'house_edge' => 0.03]);
    Game::factory()->create(['code' => 'crash-hi', 'slug' => 'crash-hi', 'type' => 'crash', 'rtp_target' => 0.99, 'house_edge' => 0.01]);

    get('/crash')->assertOk()
        ->assertSee('id="rules-btn"', false)
        ->assertSee('const RTP = "0.9700"', false);
    get('/crash?game=crash-hi')->assertOk()->assertSee('const RTP = "0.9900"', false);
    get('/crash?game=nope')->assertOk()->assertSee('const RTP = "0.9700"', false);
});

it('opens with the Majestic Games intro, which the studio intro waits for, unless it is switched off', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'type' => 'crash']);

    get('/crash')->assertOk()
        ->assertSeeInOrder(['id="mgi"', 'window.orangeIntro = {', 'id="intro"', 'window.orangeIntro.done'], false);

    config()->set('games.provider_intro.enabled', false);
    get('/crash')->assertOk()->assertDontSee('id="mgi"', false)->assertSee('id="intro"', false);
});

it('plays each crash game on its own rounds, channel and saved state', function (): void {
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'type' => 'crash', 'name' => 'Orange Crash']);
    $duck = Game::factory()->create(['code' => 'lucky-duck', 'slug' => 'lucky-duck', 'type' => 'crash', 'name' => 'Lucky Duck', 'config' => ['scene' => 'marsh']]);

    $crashed = fn (Game $game, string $point) => GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'crash_point' => $point]);
    $crashOver = $crashed($crash, '2.50');
    $duckOver = $crashed($duck, '7.25');
    $duckLive = GameRound::factory()->create(['game_id' => $duck->id, 'state' => RoundState::Running, 'crash_point' => '99.00']);

    get('/crash?game=lucky-duck')->assertOk()
        ->assertViewHas('scene', 'marsh')
        ->assertViewHas('roundChannel', 'game.lucky-duck')
        ->assertViewHas('recentCrashes', [['round_uuid' => $duckOver->round_uuid, 'crash_point' => 7.25]])
        ->assertViewHas('liveRounds', ['current' => $duckLive->round_uuid, 'previous' => $duckOver->round_uuid])
        ->assertSee('Lucky Duck</title>', false)
        ->assertSee('window.Echo.channel("game.lucky-duck")', false)
        ->assertSee('"orange.embed.slots.lucky-duck"', false)
        ->assertSee('"orange.ui.theme.lucky-duck"', false)
        ->assertDontSee('window.Echo.channel("crash")', false);

    // The original game keeps its keys, so its players' saved state stays.
    get('/crash')->assertOk()
        ->assertViewHas('scene', 'dive')
        ->assertViewHas('roundChannel', 'game.crash')
        ->assertViewHas('recentCrashes', [['round_uuid' => $crashOver->round_uuid, 'crash_point' => 2.5]])
        ->assertViewHas('liveRounds', ['current' => null, 'previous' => $crashOver->round_uuid])
        ->assertSee('Deep Dive — Orange Crash</title>', false)
        ->assertSee('"orange.embed.slots"', false);
});

it('draws each crash game in its own scene', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'type' => 'crash']);
    Game::factory()->create(['code' => 'lucky-duck', 'slug' => 'lucky-duck', 'type' => 'crash', 'name' => 'Lucky Duck', 'config' => ['scene' => 'marsh']]);

    // Lucky Duck: the marsh, its art, its words and its rules; none of the dive.
    get('/crash?game=lucky-duck')->assertOk()
        ->assertSee('class="scene marsh"', false)
        ->assertSee('images/lucky-duck/duck-fly.webp', false)
        ->assertSee('images/lucky-duck/fox-boat.webp', false)
        ->assertSee('const TEXT = {"round":"flight"', false)
        ->assertSee('<span>Bang!</span>', false)
        ->assertSee('data-theme', false)
        ->assertDontSee('class="sub-wrap', false)
        ->assertDontSee('id="depth-meter"', false)
        ->assertDontSee('class="theme-opt"', false); // one skin: no picker

    // The dive: the sub, the depth meter and the skin picker; none of the marsh.
    get('/crash')->assertOk()
        ->assertSee('class="sub-wrap', false)
        ->assertSee('id="depth-meter"', false)
        ->assertSee('class="theme-opt"', false)
        ->assertSee('<span>Hull breach</span>', false)
        ->assertDontSee('class="scene marsh"', false)
        ->assertDontSee('images/lucky-duck/', false);
});

it('shows only the sea life switched on in config', function (): void {
    config()->set('crash.sea_life', ['fish' => false, 'creatures' => false, 'octopi' => false, 'jellyfish' => false]);

    get('/crash')->assertOk()
        ->assertDontSee('class="fishes"', false)
        ->assertDontSee('class="creature ', false)
        ->assertDontSee('class="jelly"', false);

    config()->set('crash.sea_life', ['fish' => true, 'creatures' => false, 'octopi' => true, 'jellyfish' => true]);

    get('/crash')->assertOk()
        ->assertSee('class="fishes"', false)
        ->assertDontSee('class="creature whale', false)
        ->assertSee('class="creature octopus', false)
        ->assertSee('class="jelly"', false);
});
