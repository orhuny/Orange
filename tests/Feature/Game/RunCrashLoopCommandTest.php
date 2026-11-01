<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\ProvablyFairService;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    // Steep growth so the running phase lasts milliseconds whatever the crash point.
    config()->set('crash.growth_rate', 1000.0);
    app()->forgetInstance(MultiplierCalculator::class);
});

it('plays a round on the engine\'s schedule from config', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    $game = Game::factory()->create(['code' => 'crash']);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('betting=0s waiting=0s')
        ->assertSuccessful();

    $round = GameRound::query()->where('game_id', $game->id)->sole();
    $math = new ProvablyFairService;
    expect($round->state)->toBe(RoundState::Settled)
        ->and($round->crash_point)->toBe($math->crashPoint($round->serverSeed->server_seed, $round->client_seed, $round->nonce, $math->divisorForGame($game)))
        ->and($round->serverSeed->isRevealed())->toBeTrue();
});

it('lets --betting and --waiting override the schedule', function (): void {
    config()->set('crash.betting_seconds', 30);
    config()->set('crash.waiting_seconds', 30);
    Game::factory()->create(['code' => 'crash']);

    artisan('crash:loop', ['--rounds' => 1, '--betting' => 0, '--waiting' => 0, '--memory' => '-1'])
        ->expectsOutputToContain('betting=0s waiting=0s')
        ->assertSuccessful();
});

it('settles orphans and retires the seeds of finished rounds at start-up', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    $game = Game::factory()->create(['code' => 'crash']);
    // A seed shared across rounds, as before seeds became per-round, and a round a killed loop left running.
    $shared = RngSeed::factory()->create();
    GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Settled, 'server_seed_id' => $shared->id]);
    $orphan = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Running, 'server_seed_id' => $shared->id, 'crash_point' => 5.0, 'started_at' => now()]);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('cleaned up 1 orphan round(s)')
        ->expectsOutputToContain('retired 1 seed(s)')
        ->assertSuccessful();

    expect($orphan->fresh()->state)->toBe(RoundState::Settled)
        ->and($shared->fresh()->isActive())->toBeFalse()
        ->and($shared->fresh()->isRevealed())->toBeTrue();
});

it('rolls back the bets left placed on orphan rounds and leaves settled bets alone', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    Http::fake(['https://wallet.test/*' => Http::response(['balance_after' => '100.00'])]);
    $game = Game::factory()->create(['code' => 'crash']);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/orange']);
    $player = Player::factory()->create(['operator_id' => $operator->id]);
    $bet = fn (GameRound $round, BetStatus $status): Bet => Bet::factory()->create([
        'round_id' => $round->id,
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'amount' => '2.50000000',
        'status' => $status,
    ]);

    // A killed loop left one round running and one crashed but not yet settled.
    $running = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Running, 'crash_point' => 5.0, 'started_at' => now()]);
    $crashed = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Crashed, 'crash_point' => 1.5, 'crashed_at' => now()]);
    $placed = $bet($running, BetStatus::Placed);
    $won = $bet($running, BetStatus::Won);
    $lost = $bet($crashed, BetStatus::Lost);
    // Another game's open round is not this loop's orphan.
    $otherRound = GameRound::factory()->create(['state' => RoundState::Betting]);
    $otherBet = $bet($otherRound, BetStatus::Placed);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('cleaned up 2 orphan round(s)')
        ->expectsOutputToContain('rolled back 1 bet(s)')
        ->assertSuccessful();

    expect($placed->fresh()->status)->toBe(BetStatus::RolledBack)
        ->and($won->fresh()->status)->toBe(BetStatus::Won)
        ->and($lost->fresh()->status)->toBe(BetStatus::Lost)
        ->and($otherBet->fresh()->status)->toBe(BetStatus::Placed)
        ->and($running->fresh()->state)->toBe(RoundState::Settled)
        ->and($crashed->fresh()->state)->toBe(RoundState::Settled)
        ->and($otherRound->fresh()->state)->toBe(RoundState::Betting);

    $tx = WalletTransaction::query()->sole();
    expect($tx->idempotency_key)->toBe("rollback:{$placed->id}")
        ->and($tx->type)->toBe(WalletTransactionType::Rollback)
        ->and($tx->bet_id)->toBe($placed->id)
        ->and($tx->player_id)->toBe($player->id)
        ->and((string) $tx->amount)->toBe('2.50000000')
        ->and($tx->currency)->toBe('EUR')
        ->and($tx->operator_callback_status)->toBe(WalletCallbackStatus::Completed);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://wallet.test/orange/wallet/rollback'
        && $request['tx_id'] === "rollback:{$placed->id}"
        && $request['amount'] === '2.50000000'
        && $request['currency'] === 'EUR'
        && $request['player_external_id'] === $player->external_player_id
        // The refund names the debit it reverses, so a late debit can be refused.
        && $request['reference'] === ['type' => 'rollback', 'bet_id' => $placed->id, 'debit_tx_id' => "bet:{$placed->idempotency_key}"]);
});

it('does not roll a bet back twice when the clean-up runs again', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    Http::fake(['https://wallet.test/*' => Http::response(['balance_after' => '100.00'])]);
    $game = Game::factory()->create(['code' => 'crash']);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/orange']);
    $orphan = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Betting, 'betting_opened_at' => now()]);
    $placed = Bet::factory()->create([
        'round_id' => $orphan->id,
        'operator_id' => $operator->id,
        'player_id' => Player::factory()->create(['operator_id' => $operator->id])->id,
    ]);

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('rolled back 1 bet(s)')
        ->assertSuccessful();

    // As if that run had been killed after refunding the bet but before settling the round.
    $orphan->refresh()->forceFill(['state' => RoundState::Betting, 'settled_at' => null])->save();

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('cleaned up 1 orphan round(s)')
        ->doesntExpectOutputToContain('rolled back')
        ->assertSuccessful();

    expect($placed->fresh()->status)->toBe(BetStatus::RolledBack)
        ->and(WalletTransaction::query()->where('idempotency_key', "rollback:{$placed->id}")->count())->toBe(1)
        ->and($orphan->fresh()->state)->toBe(RoundState::Settled);
    Http::assertSentCount(1);
});

it('does not refund a bet that a cashout won in the meantime', function (): void {
    config()->set('crash.betting_seconds', 0);
    config()->set('crash.waiting_seconds', 0);
    Http::fake(['https://wallet.test/*' => Http::response(['balance_after' => '100.00'])]);
    $game = Game::factory()->create(['code' => 'crash']);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/orange']);
    $orphan = GameRound::factory()->create(['game_id' => $game->id, 'state' => RoundState::Running, 'crash_point' => 5.0, 'started_at' => now()]);
    $placed = Bet::factory()->create([
        'round_id' => $orphan->id,
        'operator_id' => $operator->id,
        'player_id' => Player::factory()->create(['operator_id' => $operator->id])->id,
    ]);
    // A cashout flips the bet to won between the clean-up reading it and refunding it.
    Bet::retrieved(function (Bet $bet): void {
        DB::table('bets')->where('id', $bet->id)->update(['status' => BetStatus::Won->value]);
    });

    artisan('crash:loop', ['--rounds' => 1, '--memory' => '-1'])
        ->expectsOutputToContain('cleaned up 1 orphan round(s)')
        ->doesntExpectOutputToContain('rolled back')
        ->assertSuccessful();

    expect($placed->fresh()->status)->toBe(BetStatus::Won)
        ->and(WalletTransaction::query()->count())->toBe(0);
    Http::assertNothingSent();
});
