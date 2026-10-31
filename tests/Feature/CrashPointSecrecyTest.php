<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use App\Filament\Resources\GameRoundResource\Pages\ListGameRounds;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// crash_point is stored when the round starts; it must stay secret until the crash.

it('does not show the live round\'s crash point in the admin panel', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $running = GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 87.65]);
    $settled = GameRound::factory()->create(['state' => RoundState::Settled, 'crash_point' => 3.21]);

    Livewire::test(ListGameRounds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$running, $settled])
        ->assertTableColumnStateSet('crash_point', null, $running)
        ->assertTableColumnStateSet('crash_point', 3.21, $settled)
        ->assertDontSee('87.65');
});

it('does not put the live round\'s crash point in the game\'s recent crashes', function (): void {
    GameRound::factory()->create(['state' => RoundState::Settled, 'crash_point' => 3.21]);
    GameRound::factory()->create(['state' => RoundState::Running, 'crash_point' => 87.65]);

    get('/crash')->assertOk()->assertSee('3.21')->assertDontSee('87.65');
});
