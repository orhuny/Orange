<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Engines\Contracts\InstantEngine;
use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Engines\InstantResult;
use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\LinesResolver;
use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;
use Closure;
use InvalidArgumentException;

/**
 * Slots (games.type = slot): one bet is one paid spin, its tumbles or line wins and any free
 * spins it triggers, all resolved at once, so a slot is an instant game like dice. The maths lives in a
 * model (games.config.model, see SlotModels) and the look in a theme (games.config.theme).
 *
 * The stake is what the player pays: the bet for a normal spin, 1.25× it with the ante bet,
 * 100× it for a bought feature, and the variant's price for a super buy (models that have
 * one). Wins are computed in multiples of the bet and paid as multiplier = win ÷ cost, so
 * payout = stake × multiplier = bet × win.
 */
final class SlotEngine implements InstantEngine
{
    public const MODES = ['base', 'ante', 'buy', TumbleModel::SUPER];

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
     * Config: model, variant and max win (together they set the RTP, which must be the game's
     * rtp_target), theme, whether the ante bet, the bonus buy and autoplay are offered, and
     * the bet steps the page offers.
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

        // The cap changes the RTP (a lower one cuts off the biggest wins), so a game may only
        // pick a cap its variant was measured with.
        $maxWinX = $config['max_win_x'] ?? null;
        $maxWinX = $maxWinX === null || $maxWinX === '' ? intdiv($model->maxWin, 100) : $maxWinX;
        $choices = $model->maxWinChoices($model->variants[$variantId]);
        if (! is_numeric($maxWinX) || (string) (int) $maxWinX !== (string) $maxWinX || ! isset($choices[(int) $maxWinX * 100])) {
            throw new InvalidGameConfig("Variant {$variantId}'s max win can be ".implode(', ', array_map(fn (int $cap): string => number_format($cap / 100).'×', array_keys($choices))).'.');
        }
        $maxWinX = (int) $maxWinX;

        $rtp = $choices[$maxWinX * 100];
        if (abs((float) $game->rtp_target - (float) $rtp) > 0.00005) {
            throw new InvalidGameConfig("Variant {$variantId} with a max win of ".number_format($maxWinX)."× pays an RTP of {$rtp}: set the game's RTP target to {$rtp} (and its house edge to ".bcsub('1', $rtp, 4).').');
        }

        // No theme picked: the model's first.
        $ownThemes = array_filter((array) config('slots.themes', []), fn (mixed $theme): bool => is_array($theme) && ($theme['model'] ?? null) === $code);
        $theme = (string) ($config['theme'] ?? array_key_first($ownThemes) ?? '');
        $themeModel = config("slots.themes.{$theme}.model");
        if ($themeModel !== $code) {
            throw new InvalidGameConfig("Theme [{$theme}] doesn't exist or doesn't draw model [{$code}]'s symbols.");
        }

        // None listed (the admin form sends an empty list): the defaults.
        $steps = $config['bet_steps'] ?? [];
        if (is_string($steps)) {
            $steps = trim($steps) === '' ? [] : array_map('trim', explode(',', $steps));
        }
        if ($steps === []) {
            $steps = self::DEFAULT_BET_STEPS;
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

        // A super buy is priced to pay the variant's RTP at the default cap; a lower cap would cut
        // off most of what it buys, so it needs the default or higher.
        $super = [];
        if ($model->superBuyTide !== null) {
            $super = ['super' => (bool) ($config['super'] ?? true)];
            if ($super['super'] && $maxWinX * 100 < $model->maxWin) {
                throw new InvalidGameConfig('The super buy needs a max win of at least '.number_format(intdiv($model->maxWin, 100)).'×: raise the max win or switch the super buy off.');
            }
        }

        return [
            'model' => $code,
            'variant' => $variantId,
            'max_win_x' => $maxWinX,
            'theme' => $theme,
            // Lines models have no ante.
            'ante' => in_array('ante', $model->modes(), true) && (bool) ($config['ante'] ?? true),
            'buy' => (bool) ($config['buy'] ?? true),
        ] + $super + [
            'autoplay' => (bool) ($config['autoplay'] ?? true),
            'bet_steps' => $normalised,
        ];
    }

    public function maxMultiplier(Game $game): string
    {
        return bcadd((string) $this->validateConfig($game, (array) $game->config)['max_win_x'], '0', 4);
    }

    public function verifier(): FairnessVerifier
    {
        return $this->verifier;
    }

    /** Params: mode = base (default), ante, buy or super. The model, variant, max win and price are pinned to the bet. */
    public function validateParams(Game $game, array $params): array
    {
        $mode = $params['mode'] ?? 'base';
        if (! is_string($mode) || ! in_array($mode, self::MODES, true)) {
            throw new InvalidBetParams('mode must be "base", "ante", "buy" or "super".');
        }

        $config = $this->validateConfig($game, (array) $game->config);
        if ($mode === 'ante' && ! $config['ante']) {
            throw new InvalidBetParams('This game does not offer the ante bet.');
        }
        if ($mode === 'buy' && ! $config['buy']) {
            throw new InvalidBetParams('This game does not offer the bonus buy.');
        }
        if ($mode === TumbleModel::SUPER && ! ($config['super'] ?? false)) {
            throw new InvalidBetParams('This game does not offer the super buy.');
        }

        // Pinned when the bet is placed: a later change to the game can neither alter a
        // pending payout nor stop the bet from verifying.
        $model = SlotModels::get($config['model']);
        $variant = $model->variant($config['variant']);

        return [
            'mode' => $mode,
            'model' => $config['model'],
            'variant' => $config['variant'],
            'max_win_x' => $config['max_win_x'],
            'cost' => self::cost($model, $variant, $mode),
            'rtp' => $model->rtp($variant, $config['max_win_x'] * 100),
        ];
    }

    public function play(Game $game, array $params, RandomStream $rng): InstantResult
    {
        if (isset($params['model'], $params['variant'])) {
            // Pinned at placement. Bets from before the max win was a setting played the model's.
            $model = (string) $params['model'];
            $variant = (string) $params['variant'];
            $maxWinX = isset($params['max_win_x']) ? (int) $params['max_win_x'] : null;
        } else {
            $config = $this->validateConfig($game, (array) $game->config);
            [$model, $variant, $maxWinX] = [$config['model'], $config['variant'], $config['max_win_x']];
        }

        return self::resolve($model, $variant, (string) ($params['mode'] ?? 'base'), $rng->nextInt(...), $maxWinX);
    }

    /**
     * One bet's result from a stream of integers; shared by play() and the verifier.
     *
     * @param  Closure(int): int  $nextInt
     * @param  int|null  $maxWinX  the cap in multiples of the bet (default: the model's)
     */
    public static function resolve(string $modelCode, string $variantId, string $mode, Closure $nextInt, ?int $maxWinX = null): InstantResult
    {
        if (! SlotModels::has($modelCode)) {
            throw new InvalidArgumentException("Unknown slot model [{$modelCode}].");
        }
        $model = SlotModels::get($modelCode);
        $variant = $model->variant($variantId);
        $cap = $maxWinX === null ? $model->maxWin : $maxWinX * 100;
        $rtp = $model->rtp($variant, $cap);

        $result = $model instanceof LinesModel
            ? LinesResolver::resolve($model, $variant, $mode, $nextInt, true, $cap)
            : SpinResolver::resolve($model, $variant, $mode, $nextInt, true, $cap);
        $won = $result->win > 0;
        // Wins are hundredths of the bet; the cost is hundredths too (125 = 1.25× with the ante).
        $multiplier = bcdiv((string) $result->win, (string) $model->cost($mode, $variant), 4);

        return new InstantResult(
            outcome: (array) $result->outcome + [
                'max_win_x' => TumbleModel::x($cap),
                'cost' => self::cost($model, $variant, $mode),
                'win' => $won,
                'payout_multiplier' => $multiplier,
                'rtp' => $rtp,
            ],
            won: $won,
            multiplier: $won ? $multiplier : '0',
        );
    }

    /** What a mode's stake buys with a variant, in bets: "1.00", "1.25", "100.00", or a super buy's price. */
    public static function cost(TumbleModel|LinesModel $model, Variant $variant, string $mode): string
    {
        return bcdiv((string) $model->cost($mode, $variant), '100', 2);
    }
}
