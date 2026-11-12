<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;

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
