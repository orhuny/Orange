<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Filament\Resources\GameRoundResource\Pages\ListGameRounds;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

// Rounds are shared by every operator, so the round totals mix all of their
// stakes: an operator user must only see their own operator's share (D2).

/**
 * $shared has bets from both operators, $theirsOnly only from the other one and
 * $empty none. The round totals span every operator, as BetPlacementService and
 * CashoutService keep them.
 *
 * @return array{mine: Operator, theirs: Operator, shared: GameRound, theirsOnly: GameRound, empty: GameRound}
 */
function roundsWithStakes(): array
{
    $mine = Operator::factory()->create();
    $theirs = Operator::factory()->create();

    $round = fn (string $bet, string $payout): GameRound => GameRound::factory()
        ->inState(RoundState::Settled)
        ->create(['crash_point' => 2.5, 'total_bet_amount' => $bet, 'total_payout_amount' => $payout]);
    $bet = fn (Operator $operator, GameRound $round, string $amount, ?string $payout = null): Bet => tap(
        Bet::factory()->create([
            'operator_id' => $operator->id,
            'player_id' => Player::factory()->create(['operator_id' => $operator->id])->id,
            'round_id' => $round->id,
            'amount' => $amount,
        ]),
        fn (Bet $bet) => $payout === null ? null : Cashout::factory()->create(['bet_id' => $bet->id, 'payout_amount' => $payout]),
    );

    $shared = $round('100.00000000', '194.50000000');
    $bet($mine, $shared, '7.00000000', '14.50000000');
    $bet($mine, $shared, '3.00000000');
    $bet($theirs, $shared, '90.00000000', '180.00000000');

    $theirsOnly = $round('40.00000000', '0');
    $bet($theirs, $theirsOnly, '40.00000000');

    return ['mine' => $mine, 'theirs' => $theirs, 'shared' => $shared, 'theirsOnly' => $theirsOnly, 'empty' => $round('0', '0')];
}

it('shows an operator user only the rounds their operator bet on, with their own stake and payout', function (): void {
    ['mine' => $mine, 'shared' => $shared, 'theirsOnly' => $theirsOnly, 'empty' => $empty] = roundsWithStakes();
    actingAs(User::factory()->withRole('Operator Viewer')->create(['operator_id' => $mine->id]));

    Livewire::test(ListGameRounds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$shared])
        ->assertCanNotSeeTableRecords([$theirsOnly, $empty])
        ->assertTableColumnStateSet('operator_bet_amount', '10.00000000', $shared)
        ->assertTableColumnStateSet('operator_payout_amount', '14.50000000', $shared)
        ->assertTableColumnDoesNotExist('total_bet_amount')
        ->assertTableColumnDoesNotExist('total_payout_amount')
        ->assertTableColumnStateSet('crash_point', 2.5, $shared)
        ->assertSee(['Your stake', 'Your payout'])
        ->assertDontSee(['100.00', '194.50']);
});

it('gives each operator user their own share of a shared round', function (): void {
    ['theirs' => $theirs, 'shared' => $shared, 'theirsOnly' => $theirsOnly, 'empty' => $empty] = roundsWithStakes();
    actingAs(User::factory()->withRole('Operator Viewer')->create(['operator_id' => $theirs->id]));

    Livewire::test(ListGameRounds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$shared, $theirsOnly])
        ->assertCanNotSeeTableRecords([$empty])
        ->assertTableColumnStateSet('operator_bet_amount', '90.00000000', $shared)
        ->assertTableColumnStateSet('operator_payout_amount', '180.00000000', $shared)
        ->assertTableColumnStateSet('operator_bet_amount', '40.00000000', $theirsOnly)
        ->assertTableColumnStateSet('operator_payout_amount', '0.00000000', $theirsOnly);
});

it('keeps platform users seeing every round with the round totals', function (): void {
    ['shared' => $shared, 'theirsOnly' => $theirsOnly, 'empty' => $empty] = roundsWithStakes();
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(ListGameRounds::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$shared, $theirsOnly, $empty])
        ->assertTableColumnStateSet('total_bet_amount', '100.00000000', $shared)
        ->assertTableColumnStateSet('total_payout_amount', '194.50000000', $shared)
        ->assertTableColumnDoesNotExist('operator_bet_amount')
        ->assertTableColumnDoesNotExist('operator_payout_amount');
});
