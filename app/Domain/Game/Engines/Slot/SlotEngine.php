<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Engines\InstantResult;
use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;
use Closure;
use InvalidArgumentException;

/**
 * Slots (games.type = slot): one bet is one paid spin, its tumbles and any free spins it
 * triggers, all resolved at once, so a slot is an instant game like dice. The maths lives in a
 * model (games.config.model, see SlotModels) and the look in a theme (games.config.theme).
 *
 * The stake is what the player pays: the bet for a normal spin, 1.25× it with the ante bet,
 * 100× it for a bought feature. Wins are computed in multiples of the bet and paid as
 * multiplier = win ÷ cost, so payout = stake × multiplier = bet × win.
 */
final class SlotEngine implements InstantEngine
{
    public const MODES = ['base', 'ante', 'buy'];

    private const DEFAULT_BET_STEPS = ['0.20', '0.40', '0.60', '0.80', '1.00', '2.00', '3.00', '4.00', '5.00', '10.00', '20.00', '50.00', '100.00'];

    private const DECIMAL_2DP = '/^\d{1,7}(\.\d{1,2})?$/';

    public function __construct(private readonly SlotVerifier $verifier) {}

    public static function type(): string
    {
        return 'slot';
    }

    public function family(): EngineFamily
    {
        return EngineFamily::Instant;
    }

    /**
     * Config: model, variant (its RTP must be the game's rtp_target), theme, whether the ante
     * bet, the bonus buy and autoplay are offered, and the bet steps the page offers.
     */
    public function validateConfig(Game $game, array $config): array
    {
        $code = (string) ($config['model'] ?? SlotModels::codes()[0]);
        if (! SlotModels::has($code)) {
            throw new InvalidGameConfig("Unknown slot model [{$code}]. Known: ".implode(', ', SlotModels::codes()).'.');
        }
        $model = SlotModels::get($code);

        $variantId = (string) ($config['variant'] ?? $model->defaultVariant);
        if (! isset($model->variants[$variantId])) {
            throw new InvalidGameConfig("Model [{$code}] has no RTP variant [{$variantId}]. Known: ".implode(', ', array_keys($model->variants)).'.');
        }
        $rtp = $model->variants[$variantId]->rtp;
        if (abs((float) $game->rtp_target - (float) $rtp) > 0.00005) {
            throw new InvalidGameConfig("Variant {$variantId} pays an RTP of {$rtp}: set the game's RTP target to {$rtp} (and its house edge to ".bcsub('1', $rtp, 4).').');
        }

        $themes = array_keys((array) config('slots.themes', []));
        $theme = (string) ($config['theme'] ?? $themes[0] ?? '');
        $themeModel = config("slots.themes.{$theme}.model");
        if ($themeModel !== $code) {
            throw new InvalidGameConfig("Theme [{$theme}] doesn't exist or doesn't draw model [{$code}]'s symbols.");
        }

        $steps = $config['bet_steps'] ?? self::DEFAULT_BET_STEPS;
        if (is_string($steps)) {
            $steps = array_map('trim', explode(',', $steps));
        }
        $normalised = [];
        foreach ((array) $steps as $step) {
            if (! is_scalar($step) || ! preg_match(self::DECIMAL_2DP, (string) $step) || bccomp((string) $step, '0', 2) <= 0) {
                throw new InvalidGameConfig('Bet steps must be positive amounts with up to 2 decimals.');
            }
            $normalised[] = bcadd((string) $step, '0', 2);
        }
        $normalised = array_values(array_unique($normalised));
        usort($normalised, fn (string $a, string $b): int => bccomp($a, $b, 2));
        if ($normalised === []) {
            throw new InvalidGameConfig('List at least one bet step.');
        }

        return [
            'model' => $code,
            'variant' => $variantId,
            'theme' => $theme,
            'ante' => (bool) ($config['ante'] ?? true),
            'buy' => (bool) ($config['buy'] ?? true),
            'autoplay' => (bool) ($config['autoplay'] ?? true),
            'bet_steps' => $normalised,
        ];
    }

    public function maxMultiplier(Game $game): string
    {
        $model = SlotModels::get($this->validateConfig($game, (array) $game->config)['model']);

        return bcdiv((string) $model->maxWin, '100', 4);
    }

    public function verifier(): FairnessVerifier
    {
        return $this->verifier;
    }

    /** Params: mode = base (default), ante or buy. The model, variant and price are pinned to the bet. */
    public function validateParams(Game $game, array $params): array
    {
        $mode = $params['mode'] ?? 'base';
        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            throw new InvalidBetParams('mode must be "base", "ante" or "buy".');
        }

        $config = $this->validateConfig($game, (array) $game->config);
        if ($mode === 'ante' && ! $config['ante']) {
            throw new InvalidBetParams('This game does not offer the ante bet.');
        }
        if ($mode === 'buy' && ! $config['buy']) {
            throw new InvalidBetParams('This game does not offer the bonus buy.');
        }

        // Pinned when the bet is placed: a later change to the game can neither alter a
        // pending payout nor stop the bet from verifying.
        return [
            'mode' => $mode,
            'model' => $config['model'],
            'variant' => $config['variant'],
            'cost' => self::cost($mode),
            'rtp' => SlotModels::get($config['model'])->variant($config['variant'])->rtp,
        ];
    }

    public function play(Game $game, array $params, RandomStream $rng): InstantResult
    {
        $config = isset($params['model'], $params['variant']) ? [] : $this->validateConfig($game, (array) $game->config);

        return self::resolve(
            (string) ($params['model'] ?? $config['model']),
            (string) ($params['variant'] ?? $config['variant']),
            (string) ($params['mode'] ?? 'base'),
            $rng->nextInt(...),
        );
    }

    /**
     * One bet's result from a stream of integers; shared by play() and the verifier.
     *
     * @param  Closure(int): int  $nextInt
     */
    public static function resolve(string $modelCode, string $variantId, string $mode, Closure $nextInt): InstantResult
    {
        if (! SlotModels::has($modelCode)) {
            throw new InvalidArgumentException("Unknown slot model [{$modelCode}].");
        }
        $model = SlotModels::get($modelCode);
        $variant = $model->variant($variantId);

        $result = SpinResolver::resolve($model, $variant, $mode, $nextInt);
        $won = $result->win > 0;
        // Wins are hundredths of the bet; the cost is hundredths too (125 = 1.25× with the ante).
        $multiplier = bcdiv((string) $result->win, (string) TumbleModel::COSTS[$mode], 4);

        return new InstantResult(
            outcome: (array) $result->outcome + [
                'cost' => self::cost($mode),
                'win' => $won,
                'payout_multiplier' => $multiplier,
                'rtp' => $variant->rtp,
            ],
            won: $won,
            multiplier: $won ? $multiplier : '0',
        );
    }

    /** What a mode's stake buys, in bets: "1.00", "1.25", "100.00". */
    public static function cost(string $mode): string
    {
        return bcdiv((string) TumbleModel::COSTS[$mode], '100', 2);
    }
}
