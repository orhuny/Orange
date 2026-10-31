<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Dice;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Engines\InstantResult;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;

/**
 * Dice: roll 0.00–99.99 (10 000 equally likely values); bet that it lands under or over
 * a target. The win chance follows from the target, and the multiplier from the chance:
 * floor4(rtp × 100 / chance). Under T wins if roll < T, over T wins if roll >= T, so
 * the chance is exactly T% (under) or (100 − T)% (over).
 */
final class DiceEngine implements InstantEngine
{
    /** Number of possible rolls (0.00 … 99.99). */
    public const OUTCOMES = 10_000;

    private const DECIMAL_2DP = '/^\d{1,2}(\.\d{1,2})?$/';

    public function __construct(private readonly DiceVerifier $verifier) {}

    public static function type(): string
    {
        return 'dice';
    }

    public function family(): EngineFamily
    {
        return EngineFamily::Instant;
    }

    /** Config: min_chance and max_chance in percent, 2 dp (defaults 0.01 and 98.00). */
    public function validateConfig(Game $game, array $config): array
    {
        $min = (string) ($config['min_chance'] ?? '0.01');
        $max = (string) ($config['max_chance'] ?? '98.00');

        if (! preg_match(self::DECIMAL_2DP, $min) || ! preg_match(self::DECIMAL_2DP, $max)
            || bccomp($min, '0.01', 2) < 0 || bccomp($max, '99.99', 2) > 0 || bccomp($min, $max, 2) >= 0) {
            throw new InvalidGameConfig('Dice needs 0.01 <= min_chance < max_chance <= 99.99 (percent, 2 decimals).');
        }

        return ['min_chance' => bcadd($min, '0', 2), 'max_chance' => bcadd($max, '0', 2)];
    }

    public function maxMultiplier(Game $game): string
    {
        return self::multiplier(self::rtp($game), $this->validateConfig($game, (array) $game->config)['min_chance']);
    }

    public function verifier(): FairnessVerifier
    {
        return $this->verifier;
    }

    public function validateParams(Game $game, array $params): array
    {
        $target = $params['target'] ?? null;
        $direction = $params['direction'] ?? null;

        if (! is_scalar($target) || ! preg_match(self::DECIMAL_2DP, (string) $target)) {
            throw new InvalidBetParams('target must be a number from 0.01 to 99.99 with up to 2 decimals.');
        }
        if ($direction !== 'under' && $direction !== 'over') {
            throw new InvalidBetParams('direction must be "under" or "over".');
        }

        $normalised = ['target' => bcadd((string) $target, '0', 2), 'direction' => $direction];
        $chance = self::chance($normalised);
        ['min_chance' => $min, 'max_chance' => $max] = $this->validateConfig($game, (array) $game->config);

        if (bccomp($chance, $min, 2) < 0 || bccomp($chance, $max, 2) > 0) {
            throw new InvalidBetParams("The win chance must be between {$min}% and {$max}%; this bet's is {$chance}%.");
        }

        return $normalised;
    }

    public function play(Game $game, array $params, RandomStream $rng): InstantResult
    {
        return self::resolve(self::rtp($game), $params, $rng->nextInt(self::OUTCOMES));
    }

    /**
     * The result of a roll (0 … 9999) for normalised params; shared by play() and the verifier.
     *
     * @param  array<string, mixed>  $params
     */
    public static function resolve(string $rtp, array $params, int $roll): InstantResult
    {
        $threshold = (int) bcmul((string) $params['target'], '100', 0);
        $won = $params['direction'] === 'under' ? $roll < $threshold : $roll >= $threshold;
        $multiplier = self::multiplier($rtp, self::chance($params));

        return new InstantResult(
            outcome: [
                'roll' => sprintf('%d.%02d', intdiv($roll, 100), $roll % 100),
                'target' => (string) $params['target'],
                'direction' => (string) $params['direction'],
                'win' => $won,
                'payout_multiplier' => $multiplier,
            ],
            won: $won,
            multiplier: $won ? $multiplier : '0',
        );
    }

    /**
     * The win chance in percent (2 dp) for normalised params.
     *
     * @param  array<string, mixed>  $params
     */
    public static function chance(array $params): string
    {
        return $params['direction'] === 'under'
            ? bcadd((string) $params['target'], '0', 2)
            : bcsub('100', (string) $params['target'], 2);
    }

    /** floor4(rtp × 100 / chance): bcdiv truncates, which floors positive numbers. */
    public static function multiplier(string $rtp, string $chance): string
    {
        return bcdiv(bcmul($rtp, '100', 8), $chance, 4);
    }

    public static function rtp(Game $game): string
    {
        return number_format((float) $game->rtp_target, 4, '.', '');
    }
}
