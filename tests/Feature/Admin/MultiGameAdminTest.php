<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Limits\OperatorLimitsEditor;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Models\OperatorGameLimit;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Filament\Resources\BetResource\Pages\ListBets;
use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Filament\Resources\GameResource\RelationManagers\LimitsRelationManager;
use App\Filament\Resources\GameResource\RelationManagers\OperatorsRelationManager;
use App\Filament\Resources\PlayerSeedResource\Pages\ListPlayerSeeds;
use App\Filament\Widgets\GameKpiTable;
use App\Models\User;
use App\Support\AccessControl;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function adminDice(array $attributes = []): Game
{
    return Game::factory()->create(['code' => 'dice', 'slug' => 'dice', 'type' => 'dice', 'name' => 'Dice', 'access' => GameAccess::Listed,
        'rtp_target' => 0.99, 'house_edge' => 0.01, 'config' => ['min_chance' => '0.01', 'max_chance' => '98.00']] + $attributes);
}

function operatorStaff(Operator $operator, string $role = 'Operator Admin'): User
{
    return User::factory()->withRole($role)->create(['operator_id' => $operator->id]);
}

// --- Games resource ------------------------------------------------------------------------------

it('creates a game with engine settings validated by its engine', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(CreateGame::class)
        ->fillForm(['type' => 'dice', 'code' => 'dice-two', 'slug' => 'dice-two', 'name' => 'Dice Two', 'status' => 'draft', 'access' => 'listed',
            'rtp_target' => 0.98, 'house_edge' => 0.02, 'config' => ['min_chance' => '1', 'max_chance' => '90']])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Game::query()->where('code', 'dice-two')->sole()->config)->toBe(['min_chance' => '1.00', 'max_chance' => '90.00']);

    Livewire::test(CreateGame::class)
        ->fillForm(['type' => 'dice', 'code' => 'dice-bad', 'slug' => 'dice-bad', 'name' => 'Bad', 'status' => 'draft', 'access' => 'listed',
            'rtp_target' => 0.98, 'house_edge' => 0.02, 'config' => ['min_chance' => '50', 'max_chance' => '10']])
        ->call('create')
        ->assertHasErrors(['data.config']);
    expect(Game::query()->where('code', 'dice-bad')->exists())->toBeFalse();
});

it('shows operator users only the games their operator may offer, read-only', function (): void {
    $operator = Operator::factory()->create();
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $dice = adminDice();
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $dice->id]);
    $notEnabled = Game::factory()->create(['code' => 'listed-off', 'slug' => 'listed-off', 'access' => GameAccess::Listed]);
    $switchedOff = Game::factory()->create(['code' => 'crash-off', 'slug' => 'crash-off']);
    OperatorGame::factory()->disabled()->create(['operator_id' => $operator->id, 'game_id' => $switchedOff->id]);
    $draft = Game::factory()->create(['code' => 'draft', 'slug' => 'draft', 'status' => 'draft']);
    actingAs(operatorStaff($operator));

    Livewire::test(ListGames::class)->loadTable()
        ->assertCanSeeTableRecords([$crash, $dice])
        ->assertCanNotSeeTableRecords([$notEnabled, $switchedOff, $draft]);
    get("/admin/games/{$dice->id}")->assertOk();
    get("/admin/games/{$dice->id}/edit")->assertForbidden();
    get("/admin/games/{$notEnabled->id}")->assertNotFound();
    expect(OperatorsRelationManager::canViewForRecord($dice, EditGame::class))->toBeFalse()
        ->and(LimitsRelationManager::canViewForRecord($dice, EditGame::class))->toBeTrue();
});

it('lets operator users tighten, never loosen, their own limits', function (): void {
    $operator = Operator::factory()->create();
    $dice = adminDice();
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $dice->id]);
    GameLimit::factory()->create(['game_id' => $dice->id, 'currency' => 'EUR', 'min_bet' => '0.10', 'max_bet' => '100', 'max_win' => '10000']);
    actingAs(operatorStaff($operator));

    Livewire::test(ListGames::class)->loadTable()
        ->assertActionVisible(TestAction::make('ownLimits')->table($dice))
        ->callAction(TestAction::make('ownLimits')->table($dice), data: ['limits' => [['currency' => 'eur', 'min_bet' => '1', 'max_bet' => '50', 'max_win' => '2000']]]);
    expect(OperatorGameLimit::query()->sole()->only(['currency', 'min_bet', 'max_bet', 'max_win']))
        ->toBe(['currency' => 'EUR', 'min_bet' => '1.00000000', 'max_bet' => '50.00000000', 'max_win' => '2000.00000000']);

    Livewire::test(ListGames::class)->loadTable()
        ->callAction(TestAction::make('ownLimits')->table($dice), data: ['limits' => [['currency' => 'EUR', 'max_bet' => '500']]]);
    expect((string) OperatorGameLimit::query()->sole()->max_bet)->toBe('50.00000000');
});

it('validates operator limits in one place for every caller', function (): void {
    $operator = Operator::factory()->create();
    $dice = adminDice();
    GameLimit::factory()->create(['game_id' => $dice->id, 'currency' => 'EUR', 'min_bet' => '0.10', 'max_bet' => '100', 'max_win' => '10000']);
    $editor = app(OperatorLimitsEditor::class);

    // Never the way a listed game gets enabled.
    expect(fn () => $editor->replace($operator->id, $dice, [['currency' => 'EUR', 'max_bet' => '10']]))->toThrow(InvalidArgumentException::class, 'enabled');
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $dice->id]);

    foreach ([
        [['currency' => 'TRY', 'max_bet' => '10']],
        [['currency' => 'EUR', 'min_bet' => '0.05']],
        [['currency' => 'EUR', 'max_win' => '20000']],
        [['currency' => 'EUR', 'min_bet' => '20', 'max_bet' => '10']],
        [['currency' => 'EUR'], ['currency' => 'eur']],
        [['currency' => 'EUR', 'max_bet' => '1e3']],
    ] as $rows) {
        expect(fn () => $editor->replace($operator->id, $dice, $rows))->toThrow(InvalidArgumentException::class);
    }

    $editor->replace($operator->id, $dice, [['currency' => 'EUR', 'min_bet' => '0.50', 'max_bet' => '', 'max_win' => '500']]);
    expect(OperatorGameLimit::query()->sole()->max_bet)->toBeNull();
});

it('lets platform staff with game_access.manage enable games for operators; viewers only look', function (): void {
    $dice = adminDice();
    actingAs(User::factory()->withRole('Viewer')->create());
    expect(OperatorsRelationManager::canViewForRecord($dice, EditGame::class))->toBeTrue();
    Livewire::test(OperatorsRelationManager::class, ['ownerRecord' => $dice, 'pageClass' => EditGame::class])
        ->assertActionHidden(TestAction::make('create')->table());

    actingAs(User::factory()->withRole('Admin')->create());
    $operator = Operator::factory()->create();
    Livewire::test(OperatorsRelationManager::class, ['ownerRecord' => $dice, 'pageClass' => EditGame::class])
        ->callAction(TestAction::make('create')->table(), data: ['operator_id' => $operator->id, 'enabled' => true, 'settings' => ['play_mode' => 'direct']]);

    expect(OperatorGame::query()->sole()->only(['operator_id', 'enabled', 'settings']))
        ->toBe(['operator_id' => $operator->id, 'enabled' => true, 'settings' => ['play_mode' => 'direct']]);
});

// --- reports ---------------------------------------------------------------------------------------

it('filters bets by game and shows an outcome only for resolved bets', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $dice = adminDice();
    $won = Bet::factory()->create(['round_id' => null, 'game_id' => $dice->id, 'status' => BetStatus::Won,
        'outcome' => ['roll' => '44.30', 'target' => '50.00', 'direction' => 'under', 'win' => true], 'multiplier' => '1.98', 'payout_amount' => '3.96']);
    $pending = Bet::factory()->create(['round_id' => null, 'game_id' => $dice->id, 'status' => BetStatus::Pending]);
    $crashBet = Bet::factory()->create();

    Livewire::test(ListBets::class)->loadTable()
        ->filterTable('game', $dice->id)
        ->assertCanSeeTableRecords([$won, $pending])
        ->assertCanNotSeeTableRecords([$crashBet])
        ->assertTableColumnStateSet('outcome', 'roll 44.30 · under 50.00', $won)
        ->assertTableColumnStateSet('outcome', null, $pending);
});

it('reports per-game KPIs from settled bets, limited to an operator user\'s own', function (): void {
    $mine = Operator::factory()->create();
    $theirs = Operator::factory()->create();
    $dice = adminDice();
    OperatorGame::factory()->create(['operator_id' => $mine->id, 'game_id' => $dice->id]);
    $bet = fn (Operator $op, BetStatus $status, string $amount, string $payout) => Bet::factory()->create([
        'round_id' => null, 'game_id' => $dice->id, 'operator_id' => $op->id,
        'player_id' => Player::factory()->create(['operator_id' => $op->id])->id,
        'status' => $status, 'amount' => $amount, 'payout_amount' => $payout, 'settled_at' => now(),
    ]);
    $bet($mine, BetStatus::Won, '10', '19.80');
    $bet($mine, BetStatus::Lost, '30', '0');
    $bet($mine, BetStatus::Rejected, '500', '0');
    $bet($theirs, BetStatus::Lost, '1000', '0');

    actingAs(operatorStaff($mine, 'Operator Viewer'));
    Livewire::test(GameKpiTable::class)
        ->assertCanSeeTableRecords([$dice])
        ->assertTableColumnStateSet('settled_bets', 2, $dice)
        ->assertTableColumnStateSet('ggr', '20.20', $dice)
        ->assertTableColumnStateSet('rtp', '49.50%', $dice);

    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    Livewire::test(GameKpiTable::class)->assertTableColumnStateSet('ggr', '1020.20', $dice);
});

it('shows player seeds to platform staff only, and the server seed only once revealed', function (): void {
    $operator = Operator::factory()->create();
    $player = Player::factory()->create(['operator_id' => $operator->id]);
    PlayerSeed::create(['operator_id' => $operator->id, 'player_id' => $player->id, 'server_seed' => 'still-secret-seed-value',
        'server_seed_hash' => hash('sha256', 'still-secret-seed-value'), 'client_seed' => 'c', 'nonce' => 3, 'active' => true]);
    PlayerSeed::create(['operator_id' => $operator->id, 'player_id' => Player::factory()->create(['operator_id' => $operator->id])->id,
        'server_seed' => 'already-revealed-seed', 'server_seed_hash' => hash('sha256', 'already-revealed-seed'), 'client_seed' => 'c',
        'nonce' => 9, 'active' => false, 'revealed_at' => now()]);

    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    Livewire::test(ListPlayerSeeds::class)->loadTable()
        ->assertDontSee('still-secret')
        ->assertSee('already-revealed');

    actingAs(operatorStaff($operator));
    get('/admin/player-seeds')->assertForbidden();
});

it('gives the starter roles the multi-game permissions they define', function (): void {
    expect(AccessControl::OPERATOR_PERMISSIONS)->toContain('games.view')->toContain('game_limits.tighten');

    $permissions = fn (string $role): array => Role::query()->where('name', $role)->sole()->permissions->pluck('name')->all();
    expect($permissions('Operator Admin'))->toContain('game_limits.tighten')
        ->and(in_array('game_access.manage', $permissions('Operator Admin'), true))->toBeFalse()
        ->and($permissions('Admin'))->toContain('game_access.manage')->toContain('player_seeds.view');
});

it('keeps a crash game\'s existing settings when it is edited', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'config' => ['growth_rate' => 0.06, 'max_win' => 100000]]);

    Livewire::test(EditGame::class, ['record' => $crash->getRouteKey()])
        ->fillForm(['name' => 'DeepDive'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($crash->fresh()->name)->toBe('DeepDive')
        ->and($crash->fresh()->config)->toBe(['growth_rate' => 0.06, 'max_win' => 100000]);
});
