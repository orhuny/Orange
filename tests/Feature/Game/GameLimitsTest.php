<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Limits\EffectiveLimits;
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Models\OperatorGameLimit;
use App\Domain\Operator\Models\Operator;

function limits(): GameLimits
{
    return new GameLimits;
}

it('leaves a game without limit rows unlimited in any currency (crash today)', function (): void {
    $game = Game::factory()->create();

    expect(limits()->for($game, Operator::factory()->create(), 'try'))
        ->toEqual(new EffectiveLimits('TRY', null, null, null));
});

it('uses the game\'s limits for the currency and rejects currencies it has none for', function (): void {
    $game = Game::factory()->create();
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.1', 'max_bet' => '100', 'max_win' => '10000']);
    $operator = Operator::factory()->create();

    expect(limits()->for($game, $operator, 'EUR'))
        ->toEqual(new EffectiveLimits('EUR', '0.10000000', '100.00000000', '10000.00000000'))
        ->and(fn () => limits()->for($game, $operator, 'TRY'))->toThrow(CurrencyNotSupported::class);
});

it('lets an operator only tighten the game\'s limits', function (): void {
    $game = Game::factory()->create();
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.1', 'max_bet' => '100', 'max_win' => '10000']);
    $operator = Operator::factory()->create();
    $operatorGame = OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    // Stricter min and max win, looser (ignored) max bet.
    OperatorGameLimit::factory()->create(['operator_game_id' => $operatorGame->id, 'currency' => 'EUR', 'min_bet' => '1', 'max_bet' => '500', 'max_win' => '2500']);

    expect(limits()->for($game, $operator, 'EUR'))
        ->toEqual(new EffectiveLimits('EUR', '1.00000000', '100.00000000', '2500.00000000'));
});

it('ignores an operator\'s empty limit fields', function (): void {
    $game = Game::factory()->create();
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.1', 'max_bet' => '100', 'max_win' => '10000']);
    $operator = Operator::factory()->create();
    $operatorGame = OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    OperatorGameLimit::factory()->create(['operator_game_id' => $operatorGame->id, 'currency' => 'EUR', 'max_bet' => '50']);

    expect(limits()->for($game, $operator, 'EUR'))
        ->toEqual(new EffectiveLimits('EUR', '0.10000000', '50.00000000', '10000.00000000'));
});

it('applies an operator\'s limits to a game that has none of its own', function (): void {
    $game = Game::factory()->create();
    $operator = Operator::factory()->create();
    $operatorGame = OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    OperatorGameLimit::factory()->create(['operator_game_id' => $operatorGame->id, 'currency' => 'EUR', 'max_win' => '500']);

    expect(limits()->for($game, $operator, 'EUR'))->toEqual(new EffectiveLimits('EUR', null, null, '500.00000000'))
        ->and(limits()->for($game, $operator, 'TRY'))->toEqual(new EffectiveLimits('TRY', null, null, null));
});

it('opens access=all games to every operator unless disabled for one', function (): void {
    $game = Game::factory()->create(['access' => GameAccess::All]);
    $disabled = Operator::factory()->create();
    OperatorGame::factory()->disabled()->create(['operator_id' => $disabled->id, 'game_id' => $game->id]);

    expect(limits()->for($game, Operator::factory()->create(), 'EUR'))->toBeInstanceOf(EffectiveLimits::class)
        ->and(fn () => limits()->for($game, $disabled, 'EUR'))->toThrow(GameNotAvailable::class, 'not enabled');
});

it('opens access=listed games only to operators they are enabled for', function (): void {
    $game = Game::factory()->create(['access' => GameAccess::Listed]);
    $enabled = Operator::factory()->create();
    $disabled = Operator::factory()->create();
    OperatorGame::factory()->create(['operator_id' => $enabled->id, 'game_id' => $game->id]);
    OperatorGame::factory()->disabled()->create(['operator_id' => $disabled->id, 'game_id' => $game->id]);

    expect(limits()->for($game, $enabled, 'EUR'))->toBeInstanceOf(EffectiveLimits::class)
        ->and(fn () => limits()->for($game, $disabled, 'EUR'))->toThrow(GameNotAvailable::class)
        ->and(fn () => limits()->for($game, Operator::factory()->create(), 'EUR'))->toThrow(GameNotAvailable::class);
});

it('refuses a game that is not active', function (): void {
    $game = Game::factory()->create(['status' => 'disabled']);

    $reason = null;
    try {
        limits()->for($game, Operator::factory()->create(), 'EUR');
    } catch (GameNotAvailable $e) {
        $reason = $e->reason;
    }

    expect($reason)->toBe('game_unavailable');
});

it('checks a stake against min and max bet, both inclusive', function (): void {
    $limits = new EffectiveLimits('EUR', '0.10000000', '100.00000000', null);

    limits()->assertStake($limits, '0.10');
    limits()->assertStake($limits, '100');
    expect(fn () => limits()->assertStake($limits, '0.09999999'))->toThrow(StakeOutsideLimits::class, 'minimum')
        ->and(fn () => limits()->assertStake($limits, '100.00000001'))->toThrow(StakeOutsideLimits::class, 'maximum');
});

it('caps a payout at max win', function (): void {
    $limits = new EffectiveLimits('EUR', null, null, '500.00000000');

    expect($limits->capPayout('499.99999999'))->toBe('499.99999999')
        ->and($limits->capPayout('500.00000001'))->toBe('500.00000000')
        ->and(EffectiveLimits::unlimited('EUR')->capPayout('1000000.00000000'))->toBe('1000000.00000000');
});
