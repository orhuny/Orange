<?php

declare(strict_types=1);

use function Pest\Laravel\get;

// The admin panel is branded Majestic Games (the project stays Orange), with its own
// favicon and finishing stylesheet, which need no asset build.

it('signs in under the Majestic Games brand, with its stylesheet and favicon', function (): void {
    get('/admin/login')
        ->assertOk()
        ->assertSee('Majestic Games')
        ->assertSee('css/majestic-admin.css?v=', false)
        ->assertSee('images/majestic/favicon.svg', false)
        ->assertDontSee('Orange Provider');
});
