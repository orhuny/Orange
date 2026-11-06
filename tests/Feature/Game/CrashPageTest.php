<?php

declare(strict_types=1);

use function Pest\Laravel\get;

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
