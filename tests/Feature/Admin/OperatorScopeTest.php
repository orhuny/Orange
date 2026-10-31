<?php

declare(strict_types=1);

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Models\Webhook;
use App\Filament\Resources\BetResource\Pages\ListBets;
use App\Filament\Resources\CashoutResource\Pages\ListCashouts;
use App\Filament\Resources\OperatorResource\Pages\ListOperators;
use App\Filament\Resources\PlayerResource\Pages\ListPlayers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\WebhookResource\Pages\ListWebhooks;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/** @return array{0: Bet, 1: Player, 2: Webhook, 3: Cashout} */
function dataFor(Operator $operator): array
{
    $player = Player::factory()->create(['operator_id' => $operator->id]);
    $bet = Bet::factory()->create(['operator_id' => $operator->id, 'player_id' => $player->id]);
    $cashout = Cashout::factory()->create(['bet_id' => $bet->id]);
    $webhook = Webhook::factory()->create(['operator_id' => $operator->id]);

    return [$bet, $player, $webhook, $cashout];
}

/** @return array{0: Operator, 1: Operator, 2: array{0: Bet, 1: Player, 2: Webhook, 3: Cashout}, 3: array{0: Bet, 1: Player, 2: Webhook, 3: Cashout}} */
function twoOperators(): array
{
    $mine = Operator::factory()->create();
    $theirs = Operator::factory()->create();

    return [$mine, $theirs, dataFor($mine), dataFor($theirs)];
}

function operatorUser(Operator $operator, string $role = 'Operator Viewer'): User
{
    return User::factory()->withRole($role)->create(['operator_id' => $operator->id]);
}

it('shows an operator user only their own operator in every report', function (): void {
    [$mine, $theirs, $own, $other] = twoOperators();
    actingAs(operatorUser($mine));
    [$bet, $player, $webhook, $cashout] = $own;
    [$otherBet, $otherPlayer, $otherWebhook, $otherCashout] = $other;

    Livewire::test(ListBets::class)->loadTable()->assertCanSeeTableRecords([$bet])->assertCanNotSeeTableRecords([$otherBet]);
    Livewire::test(ListPlayers::class)->loadTable()->assertCanSeeTableRecords([$player])->assertCanNotSeeTableRecords([$otherPlayer]);
    Livewire::test(ListWebhooks::class)->loadTable()->assertCanSeeTableRecords([$webhook])->assertCanNotSeeTableRecords([$otherWebhook]);
    Livewire::test(ListCashouts::class)->loadTable()->assertCanSeeTableRecords([$cashout])->assertCanNotSeeTableRecords([$otherCashout]);
    Livewire::test(ListOperators::class)->loadTable()->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
});

it('lets an operator user open their own operator read-only, never another one', function (): void {
    [$mine, $theirs] = twoOperators();
    $mine->forceFill(['secret_key' => 'abcd'.str_repeat('x', 56).'wxyz'])->save();
    actingAs(operatorUser($mine));

    get("/admin/operators/{$mine->id}")->assertOk()->assertSee($mine->id)->assertSee('abcd••••••••••••wxyz');
    get("/admin/operators/{$mine->id}/edit")->assertForbidden();
    get("/admin/operators/{$theirs->id}")->assertNotFound();
});

it('never lets an operator user past operator permissions, even with a platform role', function (): void {
    [$mine] = twoOperators();
    actingAs(operatorUser($mine, 'Admin'));

    foreach (['/admin/games', '/admin/rng-seeds', '/admin/roles', '/admin/operators/create'] as $url) {
        get($url)->assertForbidden();
    }
    get('/admin/bets')->assertOk();
});

it('lets an operator admin manage only their own operator\'s users', function (): void {
    [$mine, $theirs] = twoOperators();
    $admin = operatorUser($mine, 'Operator Admin');
    $colleague = operatorUser($mine);
    $stranger = operatorUser($theirs);
    $platform = User::factory()->withRole('Finance')->create();
    actingAs($admin);

    Livewire::test(ListUsers::class)->loadTable()
        ->assertCanSeeTableRecords([$admin, $colleague])
        ->assertCanNotSeeTableRecords([$stranger, $platform]);
    get("/admin/users/{$stranger->id}/edit")->assertNotFound();
    get("/admin/users/{$platform->id}/edit")->assertNotFound();

    // Only operator roles are offered, and the new user lands on the admin's operator.
    Livewire::test(CreateUser::class)
        ->assertSee('Operator Viewer')
        ->assertDontSee('Finance')
        ->fillForm([
            'name' => 'New Colleague',
            'email' => 'new@operator.test',
            'password' => 'correct-horse-battery',
            'is_active' => true,
            'roles' => [Role::findByName('Operator Viewer')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'new@operator.test')->firstOrFail();
    expect($created->operator_id)->toBe($mine->id)
        ->and($created->hasRole('Operator Viewer'))->toBeTrue();
});

it('locks out operator users whose operator was removed', function (): void {
    [$mine] = twoOperators();
    $user = operatorUser($mine);
    $mine->delete();

    actingAs($user);
    get('/admin')->assertForbidden();
});

it('keeps platform users seeing every operator', function (): void {
    [, , $own, $other] = twoOperators();
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(ListBets::class)->loadTable()->assertCanSeeTableRecords([$own[0], $other[0]]);
});
