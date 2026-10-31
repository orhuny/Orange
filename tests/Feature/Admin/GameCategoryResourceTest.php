<?php

declare(strict_types=1);

use App\Domain\Game\Models\GameCategory;
use App\Domain\Operator\Models\Operator;
use App\Filament\Resources\GameCategoryResource\Pages\CreateGameCategory;
use App\Filament\Resources\GameCategoryResource\Pages\ListGameCategories;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lets platform admins manage and reorder lobby categories', function (): void {
    actingAs(User::factory()->withRole('Admin')->create());

    Livewire::test(CreateGameCategory::class)
        ->fillForm(['name' => 'Table games', 'slug' => 'table', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $ids = GameCategory::query()->orderByDesc('id')->pluck('id')->map(fn ($id) => (string) $id)->all();
    Livewire::test(ListGameCategories::class)
        ->loadTable()
        ->call('reorderTable', $ids);

    expect(GameCategory::query()->where('slug', 'table')->value('sort_order'))->toBe(1);
});

it('shows categories to viewers but only lets lobby.manage change them', function (): void {
    actingAs(User::factory()->withRole('Viewer')->create());

    get('/admin/game-categories')->assertOk();
    get('/admin/game-categories/create')->assertForbidden();
});

it('keeps operator users out of lobby management', function (): void {
    expect(AccessControl::OPERATOR_PERMISSIONS)->not->toContain('lobby.view')->not->toContain('lobby.manage');
    $user = User::factory()->withRole('Operator Admin')->create(['operator_id' => Operator::factory()->create()->id]);
    actingAs($user);

    get('/admin/game-categories')->assertForbidden();
});
