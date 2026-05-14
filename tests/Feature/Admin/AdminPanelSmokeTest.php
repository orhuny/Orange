<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['email' => 'admin@orange.local']));
});

dataset('admin_resource_index_routes', [
    ['/admin/operators'],
    ['/admin/games'],
    ['/admin/game-rounds'],
    ['/admin/bets'],
    ['/admin/cashouts'],
    ['/admin/rng-seeds'],
    ['/admin/webhooks'],
    ['/admin/players'],
]);

it('admin resource index page renders for authenticated users', function (string $url): void {
    $this->get($url)->assertOk();
})->with('admin_resource_index_routes');

it('admin dashboard renders for authenticated users', function (): void {
    $this->get('/admin')->assertOk();
});

it('admin login page renders for guests', function (): void {
    auth()->logout();
    $this->get('/admin/login')->assertOk();
});
