<?php

declare(strict_types=1);

use App\Domain\Operator\Models\Operator;
use App\Filament\Resources\OperatorResource\Pages\EditOperator;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
});

it('shows the operator id and only the ends of the secret on the edit screen', function (): void {
    $secret = 'abcd'.str_repeat('x', 56).'wxyz';
    $operator = Operator::factory()->create(['secret_key' => $secret]);

    get("/admin/operators/{$operator->id}/edit")
        ->assertOk()
        ->assertSee($operator->id)
        ->assertSee('abcd••••••••••••wxyz')
        ->assertDontSee($secret);
});

it('keeps the secret when the operator is saved from the edit screen', function (): void {
    $operator = Operator::factory()->create(['secret_key' => str_repeat('s', 64)]);

    Livewire::test(EditOperator::class, ['record' => $operator->getRouteKey()])
        ->fillForm(['name' => 'Renamed operator'])
        ->call('save')
        ->assertHasNoFormErrors();

    $operator->refresh();
    expect($operator->name)->toBe('Renamed operator')
        ->and($operator->secret_key)->toBe(str_repeat('s', 64));
});
