<?php

declare(strict_types=1);

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\Wallet\Models\Webhook;
use App\Filament\Resources\BetResource\Pages\ListBets;
use App\Filament\Resources\CashoutResource\Pages\ListCashouts;
use App\Filament\Resources\GameRoundResource\Pages\ListGameRounds;
use App\Filament\Resources\OperatorResource\Pages\ListOperators;
use App\Filament\Resources\PlayerResource\Pages\ListPlayers;
use App\Filament\Resources\RngSeedResource\Pages\ListRngSeeds;
use App\Filament\Resources\WebhookResource\Pages\ListWebhooks;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    actingAs(User::factory()->create(['email' => 'qa@orange.local']));
});

dataset('reports', [
    'bets' => [ListBets::class, Bet::class, 'placed_at'],
    'cashouts' => [ListCashouts::class, Cashout::class, 'cashed_out_at'],
    'rounds' => [ListGameRounds::class, GameRound::class, 'created_at'],
    'webhooks' => [ListWebhooks::class, Webhook::class, 'created_at'],
    'players' => [ListPlayers::class, Player::class, 'created_at'],
    'operators' => [ListOperators::class, Operator::class, 'created_at'],
    'rng seeds' => [ListRngSeeds::class, RngSeed::class, 'created_at'],
]);

it('narrows every admin report to the chosen date range', function (string $page, string $model, string $column): void {
    $old = $model::factory()->create();
    $old->forceFill([$column => '2026-01-10 12:00:00'])->saveQuietly();
    $inRange = $model::factory()->create();
    $inRange->forceFill([$column => '2026-03-15 12:00:00'])->saveQuietly();

    Livewire::test($page)
        ->loadTable()
        ->filterTable($column, ['from' => '2026-03-01', 'until' => '2026-03-31'])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$old]);
})->with('reports');
