<?php

declare(strict_types=1);

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function adminUser(string $role, array $attributes = []): User
{
    return User::factory()->withRole($role)->create($attributes);
}

it('keeps users without a role or with a deactivated account out of the panel', function (): void {
    actingAs(User::factory()->create());
    get('/admin')->assertForbidden();

    actingAs(adminUser('Admin', ['is_active' => false]));
    get('/admin')->assertForbidden();
});

it('shows each starter role only what its permissions allow', function (string $role, array $allowed, array $denied): void {
    actingAs(adminUser($role));

    foreach ($allowed as $url) {
        get($url)->assertOk();
    }
    foreach ($denied as $url) {
        get($url)->assertForbidden();
    }
})->with([
    'viewer' => ['Viewer', ['/admin', '/admin/bets', '/admin/operators', '/admin/webhooks'], ['/admin/operators/create', '/admin/games/create', '/admin/users', '/admin/roles']],
    'finance' => ['Finance', ['/admin/bets', '/admin/cashouts', '/admin/players'], ['/admin/webhooks', '/admin/rng-seeds', '/admin/operators/create', '/admin/users']],
    'support' => ['Support', ['/admin/webhooks', '/admin/players'], ['/admin/rng-seeds', '/admin/games', '/admin/users']],
    'admin' => ['Admin', ['/admin/operators/create', '/admin/games/create', '/admin/rng-seeds'], ['/admin/users', '/admin/roles']],
    'super admin' => ['Super Admin', ['/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create'], []],
]);

it('lets a super admin create a user with a role who can then sign in', function (): void {
    actingAs(adminUser(AccessControl::SUPER_ADMIN));
    $finance = Role::findByName('Finance');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Fiona Finance',
            'email' => 'fiona@example.com',
            'password' => 'correct-horse-battery',
            'is_active' => true,
            'roles' => [$finance->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $fiona = User::query()->where('email', 'fiona@example.com')->firstOrFail();
    expect($fiona->hasRole('Finance'))->toBeTrue();

    actingAs($fiona);
    get('/admin/bets')->assertOk();
    get('/admin/webhooks')->assertForbidden();
});

it('blocks privilege escalation and self lock-out', function (): void {
    Role::findByName('Admin')->givePermissionTo('users.manage');
    $admin = adminUser('Admin');
    $super = adminUser(AccessControl::SUPER_ADMIN);
    actingAs($admin);

    // A non-super admin can't edit a Super Admin, can't delete themselves.
    get("/admin/users/{$super->id}/edit")->assertForbidden();
    expect(UserResource::canDelete($admin))->toBeFalse();

    // …and isn't offered the Super Admin role when creating users.
    Livewire::test(CreateUser::class)
        ->assertDontSee(AccessControl::SUPER_ADMIN);

    // Even a Super Admin can't delete their own account.
    actingAs($super);
    expect(UserResource::canDelete($super))->toBeFalse()
        ->and(UserResource::canDelete($admin))->toBeTrue();
});

it('locks the Super Admin role and applies role edits immediately', function (): void {
    actingAs(adminUser(AccessControl::SUPER_ADMIN));
    get('/admin/roles/'.Role::findByName(AccessControl::SUPER_ADMIN)->id.'/edit')->assertForbidden();

    $viewer = Role::findByName('Viewer');
    $redeliver = Permission::findByName('webhooks.redeliver');
    Livewire::test(EditRole::class, ['record' => $viewer->getRouteKey()])
        ->fillForm(['permissions' => [...$viewer->permissions->pluck('id')->all(), $redeliver->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(adminUser('Viewer')->can('webhooks.redeliver'))->toBeTrue();
});
