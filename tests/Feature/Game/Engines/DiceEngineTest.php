<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Dice\DiceEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;

function dice(): DiceEngine
{
    return app(DiceEngine::class);
}

function diceGame(array $config = ['min_chance' => '0.01', 'max_chance' => '98.00']): Game
{
    return Game::factory()->create(['type' => 'dice', 'rtp_target' => 0.99, 'house_edge' => 0.01, 'config' => $config]);
}

it('is the instant dice engine paying at most 9900x', function (): void {
    expect(DiceEngine::type())->toBe('dice')
        ->and(dice()->family())->toBe(EngineFamily::Instant)
        ->and(dice()->maxMultiplier(diceGame()))->toBe('9900.0000');
});

it('pays floor4(rtp × 100 / chance)', function (string $chance, string $multiplier): void {
    expect(DiceEngine::multiplier('0.9900', $chance))->toBe($multiplier);
})->with([
    ['50.00', '1.9800'],
    ['49.50', '2.0000'],
    ['33.33', '2.9702'],
    ['98.00', '1.0102'],
    ['0.01', '9900.0000'],
]);

it('wins on exactly chance% of the 10 000 rolls, so the RTP is the game\'s (less flooring)', function (string $target, string $direction): void {
    $params = dice()->validateParams(diceGame(), ['target' => $target, 'direction' => $direction]);
    $wins = 0;
    for ($roll = 0; $roll < DiceEngine::OUTCOMES; $roll++) {
        $wins += DiceEngine::resolve('0.9900', $params, $roll)->won ? 1 : 0;
    }

    $chance = DiceEngine::chance($params);
    expect($wins)->toBe((int) bcmul($chance, '100', 0));

    $rtp = bcmul(bcdiv((string) $wins, '10000', 8), DiceEngine::multiplier('0.9900', $chance), 8);
    expect(bccomp($rtp, '0.99', 8))->toBeLessThanOrEqual(0)
        ->and(bccomp($rtp, '0.9899', 8))->toBeGreaterThanOrEqual(0);
})->with([
    ['49.50', 'under'], ['0.01', 'under'], ['98.00', 'under'], ['12.34', 'under'],
    ['50.50', 'over'], ['2.00', 'over'], ['99.99', 'over'], ['87.66', 'over'],
]);

it('rolls the published values through play()', function (): void {
    $game = diceGame();
    $under = dice()->validateParams($game, ['target' => '50', 'direction' => 'under']);

    $result = dice()->play($game, $under, new RandomStream('orange-dice-seed-0001', 'player-seed', 1));

    expect($result->outcome)->toBe(['roll' => '44.30', 'target' => '50.00', 'direction' => 'under', 'win' => true, 'payout_multiplier' => '1.9800', 'rtp' => '0.9900'])
        ->and($result->won)->toBeTrue()
        ->and($result->multiplier)->toBe('1.9800');

    $lost = dice()->play($game, dice()->validateParams($game, ['target' => '50', 'direction' => 'over']), new RandomStream('orange-dice-seed-0001', 'player-seed', 2));
    expect($lost->outcome['roll'])->toBe('44.41')
        ->and($lost->won)->toBeFalse()
        ->and($lost->multiplier)->toBe('0');
});

it('verifies a roll exactly as play() resolved it', function (): void {
    expect(dice()->verifier()->verify(diceGame(), 'orange-dice-seed-0002', 'abc', 42, ['target' => '9', 'direction' => 'under']))
        ->toMatchArray(['roll' => '8.91', 'win' => true, 'payout_multiplier' => '11.0000', 'server_seed_hash' => hash('sha256', 'orange-dice-seed-0002')]);
});

it('refuses bad targets, directions and chances outside the configured range', function (array $params): void {
    expect(fn () => dice()->validateParams(diceGame(['min_chance' => '1.00', 'max_chance' => '95.00']), $params))->toThrow(InvalidBetParams::class);
})->with([
    'no target' => [['direction' => 'under']],
    'bad direction' => [['target' => '50', 'direction' => 'sideways']],
    'three decimals' => [['target' => '50.001', 'direction' => 'under']],
    'negative' => [['target' => '-5', 'direction' => 'under']],
    'chance too small' => [['target' => '0.99', 'direction' => 'under']],
    'chance too big (over)' => [['target' => '4.99', 'direction' => 'over']],
]);

it('validates its config', function (): void {
    expect(dice()->validateConfig(diceGame(), ['min_chance' => '1', 'max_chance' => '95.5']))->toBe(['min_chance' => '1.00', 'max_chance' => '95.50'])
        ->and(fn () => dice()->validateConfig(diceGame(), ['min_chance' => '50', 'max_chance' => '10']))->toThrow(InvalidGameConfig::class)
        ->and(fn () => dice()->validateConfig(diceGame(), ['max_chance' => '100']))->toThrow(InvalidGameConfig::class);
});

it('fixes the RTP when the bet is placed, and verifies with it after the game changes', function (): void {
    $game = diceGame();
    $params = dice()->validateParams($game, ['target' => '50', 'direction' => 'under', 'rtp' => '0.5']);
    expect($params['rtp'])->toBe('0.9900');

    $game->forceFill(['rtp_target' => 0.97, 'house_edge' => 0.03])->save();
    $result = dice()->play($game, $params, new RandomStream('orange-dice-seed-0001', 'player-seed', 1));

    expect($result->multiplier)->toBe('1.9800')
        ->and(dice()->verifier()->verify($game, 'orange-dice-seed-0001', 'player-seed', 1, ['target' => '50', 'direction' => 'under', 'rtp' => '0.9900']))
        ->toMatchArray(['roll' => '44.30', 'payout_multiplier' => '1.9800', 'rtp' => '0.9900'])
        ->and(dice()->verifier()->verify($game, 'orange-dice-seed-0001', 'player-seed', 1, ['target' => '50', 'direction' => 'under']))
        ->toMatchArray(['payout_multiplier' => '1.9400']);
});
