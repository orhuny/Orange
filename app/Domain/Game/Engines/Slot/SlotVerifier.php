<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;
use Illuminate\Validation\ValidationException;

/**
 * Replays a slot bet from a revealed seed pair. describe() publishes everything needed to
 * do it independently: the draw order, the paytable and every weight table of the variant.
 * The game page also shows its paytable and rules from it.
 */
final class SlotVerifier implements FairnessVerifier
{
    public const ALGORITHM = 'hmac-sha256-stream-tumble-slot';

    public const LINES_ALGORITHM = 'hmac-sha256-stream-lines-slot';

    public function describe(Game $game): array
    {
        $config = app(SlotEngine::class)->validateConfig($game, (array) $game->config);
        $model = SlotModels::get($config['model']);
        if ($model instanceof LinesModel) {
            return $this->describeLines($model, $config);
        }
        $variant = $model->variant($config['variant']);
        $cap = $config['max_win_x'] * 100;
        $rtp = $model->rtp($variant, $cap);
        $letters = fn (WeightTable $table): array => array_combine(
            array_map(fn (int $symbol): string => $model->symbols[$symbol], array_keys($table->weights())),
            array_values($table->weights()),
        );

        $paytable = [];
        foreach ($model->pays as $symbol => $tiers) {
            $paytable[] = [
                'symbol' => $model->symbols[$symbol],
                'name' => $model->symbolNames[$symbol],
                'pays' => array_combine(
                    array_map(fn (int $i): string => $i + 1 < count($model->payTiers) ? $model->payTiers[$i].'-'.($model->payTiers[$i + 1] - 1) : $model->payTiers[$i].'+', array_keys($tiers)),
                    array_map(fn (int $h): string => TumbleModel::x($h), $tiers),
                ),
            ];
        }

        $multipliers = match (true) {
            $model->freeSpinMultiplier === TumbleModel::TIDE => 'In free spins the multiplier sum of every spin that pays joins the tide (a running total from 0), '
                .'and a spin that pays is multiplied by the tide once it is above 0. ',
            default => 'In free spins a spin that pays is multiplied by the sum of the multiplier values on its final grid. ',
        };
        if ($model->baseMultipliers) {
            $multipliers = 'Outside free spins a multiplier cell (M) draws its value from "base_multipliers"; a spin that pays has its line win '
                .'(not the scatter pay) multiplied by the sum of the multiplier values on its final grid. '.$multipliers;
        }

        // Only a model with a super buy says so, so the others publish exactly what they always have.
        $super = $model->superBuyTide !== null;
        $superPrice = $super ? SlotEngine::cost($model, $variant, TumbleModel::SUPER) : '';
        if ($super) {
            $multipliers .= "A super buy is a bought spin whose free spins start with the tide at {$model->superBuyTide} instead of 0. ";
        }

        return [
            'algorithm' => self::ALGORITHM,
            'model' => $model->code,
            'variant' => $variant->id,
            'rtp' => $rtp,
            'house_edge' => bcsub('1', $rtp, 4),
            'inputs' => [
                'server_seed' => 'revealed when the player rotates their seed pair (its SHA-256 is shown beforehand)',
                'client_seed' => "the player's own client seed",
                'nonce' => 'the bet number on that seed pair, shown with every bet',
                'mode' => ($super ? 'base, ante, buy or super' : 'base, ante or buy').' (in the bet\'s outcome)',
                'variant' => 'the RTP variant the bet was played with (in its outcome); defaults to the game\'s current one',
                'max_win_x' => 'the max win the bet was played with (in its outcome); defaults to the game\'s current one',
            ],
            'formula' => 'nextInt(n): read unsigned 32-bit big-endian integers from block_k = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}:{k}"), '
                .'k = 0, 1, …, eight per block; take the first u < floor(2^32 / n) * n, then u mod n. '
                .'A weighted draw calls nextInt(total) once and lands on the first entry (in the listed order) whose running total exceeds it. '
                ."The grid is {$model->cols} columns × {$model->rows} rows; cells are drawn column 0 first, each column top to bottom, "
                .'from that column\'s table ("base", "ante", or in free spins "free"); a multiplier cell (M) draws its value from "multipliers" right after it. '
                .($super ? 'A bought spin (buy or super)' : 'A bought spin').' first draws the scatter count from "buy_scatters", then picks their cells by a partial Fisher–Yates shuffle '
                ."of 0…{$model->cells}-1 (for i = 0…count-1: swap i with i + nextInt({$model->cells} - i); cell = column × {$model->rows} + row), "
                .'then draws every other cell in order from "buy". '
                ."Every pay symbol with at least {$model->payTiers[0]} copies anywhere pays its tier; they are removed, the rest fall, and the gaps refill "
                .'(columns left to right, new cells top to bottom, same draws); repeat until nothing pays. '
                ."Scatters (S) are counted on the final grid: {$model->freeSpinsTriggerAt}+ pay the scatter pay and award {$model->freeSpinsAwarded} free spins; "
                ."in free spins {$model->retriggerAt}+ add {$model->retriggerAwarded} (at most {$model->maxFreeSpins} in all). "
                .$multipliers
                .'A bet\'s total win stops at max_win and the feature ends there. Wins are multiples of the bet; '
                .'payout_multiplier = floor4(total win / cost), cost = 1 (base), 1.25 (ante)'.($super ? ", 100 (buy) or {$superPrice} (super)." : ' or 100 (buy).'),
            'grid' => ['columns' => $model->cols, 'rows' => $model->rows],
            'symbols' => array_combine($model->symbols, $model->symbolNames),
            'paytable' => $paytable,
            'scatter_pays' => array_map(fn (int $h): string => TumbleModel::x($h), $model->scatterPays),
            'free_spins' => [
                'trigger_scatters' => $model->freeSpinsTriggerAt, 'awarded' => $model->freeSpinsAwarded,
                'retrigger_scatters' => $model->retriggerAt, 'retrigger_awarded' => $model->retriggerAwarded, 'max' => $model->maxFreeSpins,
            ] + ($super ? ['super_buy_tide' => $model->superBuyTide] : []),
            'max_win' => TumbleModel::x($cap),
            'multipliers' => ['base_game' => $model->baseMultipliers, 'free_spins' => $model->freeSpinMultiplier],
            'costs' => array_combine($model->modes(), array_map(fn (string $mode): string => SlotEngine::cost($model, $variant, $mode), $model->modes())),
            'features' => ['ante' => $config['ante'], 'buy' => $config['buy']] + ($super ? ['super' => $config['super']] : []),
            'tables' => [
                'base' => array_map($letters, $variant->base),
                'ante' => array_map($letters, $variant->ante),
                'buy' => array_map($letters, $variant->buy),
                'free' => array_map($letters, $variant->free),
                'multipliers' => $variant->multipliers->weights(),
                'buy_scatters' => $variant->buyScatters->weights(),
            ] + ($model->baseMultipliers ? ['base_multipliers' => ($variant->baseMultipliers ?? $variant->multipliers)->weights()] : []),
        ];
    }

    /**
     * describe() for a paylines model: the lines, the paytable by count, the wilds and the amore.
     *
     * @param  array<string, mixed>  $config  SlotEngine::validateConfig()
     * @return array<string, mixed>
     */
    private function describeLines(LinesModel $model, array $config): array
    {
        $variant = $model->variant((string) $config['variant']);
        $cap = (int) $config['max_win_x'] * 100;
        $rtp = $model->rtp($variant, $cap);
        $letters = fn (WeightTable $table): array => array_combine(
            array_map(fn (int $symbol): string => $model->symbols[$symbol], array_keys($table->weights())),
            array_values($table->weights()),
        );
        $lineCount = count($model->lines);
        $firstTier = min(array_keys($model->freeSpinsAwarded));

        $paytable = [];
        foreach ($model->pays as $symbol => $byCount) {
            $counts = array_keys($byCount);
            $paytable[] = [
                'symbol' => $model->symbols[$symbol],
                'name' => $model->symbolNames[$symbol],
                // × the bet (all lines), as the page shows it; line_pays: × the line bet (the bet ÷ lines).
                'pays' => array_combine(array_map('strval', $counts), array_map(fn (int $count): string => TumbleModel::x($model->linePay($symbol, $count)), $counts)),
                'line_pays' => array_combine(array_map('strval', $counts), array_map('strval', array_values($byCount))),
            ];
        }
        $wild = $model->symbols[$model->wild];
        $scatter = $model->symbols[$model->scatter];

        return [
            'algorithm' => self::LINES_ALGORITHM,
            'model' => $model->code,
            'variant' => $variant->id,
            'rtp' => $rtp,
            'house_edge' => bcsub('1', $rtp, 4),
            'inputs' => [
                'server_seed' => 'revealed when the player rotates their seed pair (its SHA-256 is shown beforehand)',
                'client_seed' => "the player's own client seed",
                'nonce' => 'the bet number on that seed pair, shown with every bet',
                'mode' => "base or buy (in the bet's outcome)",
                'variant' => "the RTP variant the bet was played with (in its outcome); defaults to the game's current one",
                'max_win_x' => "the max win the bet was played with (in its outcome); defaults to the game's current one",
            ],
            'formula' => 'nextInt(n): read unsigned 32-bit big-endian integers from block_k = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}:{k}"), '
                .'k = 0, 1, …, eight per block; take the first u < floor(2^32 / n) * n, then u mod n. '
                .'A weighted draw calls nextInt(total) once and lands on the first entry (in the listed order) whose running total exceeds it. '
                ."The screen is {$model->cols} reels × {$model->rows} rows; cells are drawn reel 0 first, each reel top to bottom, "
                ."from that reel's table (\"base\", or in free spins \"free\"); a wild ({$wild}) draws its value right after it "
                .'from "base_wilds" (in free spins "free_wilds"): 1 is a plain wild, more is a multiplier. '
                .'A bought spin first draws the scatter count from "buy_scatters", then picks their cells by a partial Fisher–Yates shuffle '
                ."of 0…{$model->cells}-1 (for i = 0…count-1: swap i with i + nextInt({$model->cells} - i); cell = reel × {$model->rows} + row), "
                .'then draws every other cell in order from "buy". '
                ."Each of the {$lineCount} lines (\"lines\": the row on each reel, row 0 at the top) pays left to right: the symbol on reel 0 "
                ."and the run of it or wilds from reel 0; a run the paytable lists pays its line pay (× the line bet, the bet ÷ {$lineCount}), "
                .'times the sum of the multipliers of the wilds in the run (1 when it has none). '
                ."Scatters ({$scatter}) anywhere pay the scatter pay (never multiplied) and award free spins; "
                ."in free spins {$model->retriggerAt}+ add {$model->retriggerAwarded} (at most {$model->maxFreeSpins} in all). "
                ."In free spins the amore starts at 1, every wild that lands adds 1 to it before the spin pays, and it multiplies the spin's line wins. "
                ."A bet's total win stops at max_win and the feature ends there. Wins are multiples of the bet; "
                .'payout_multiplier = floor4(total win / cost), cost = 1 (base) or 100 (buy).',
            'grid' => ['columns' => $model->cols, 'rows' => $model->rows],
            'lines' => $model->lines,
            'symbols' => array_combine($model->symbols, $model->symbolNames),
            'paytable' => $paytable,
            'scatter_pays' => array_map(fn (int $h): string => TumbleModel::x($h), $model->scatterPays),
            'free_spins' => [
                'trigger_scatters' => $firstTier, 'awarded' => $model->freeSpinsAwarded[$firstTier], 'awarded_by_count' => $model->freeSpinsAwarded,
                'retrigger_scatters' => $model->retriggerAt, 'retrigger_awarded' => $model->retriggerAwarded, 'max' => $model->maxFreeSpins,
            ],
            'wilds' => [
                'base_game' => array_keys(($variant->baseMultipliers ?? $variant->multipliers)->weights()),
                'free_spins' => array_keys($variant->multipliers->weights()),
            ],
            'max_win' => TumbleModel::x($cap),
            'costs' => array_combine($model->modes(), array_map(fn (string $mode): string => SlotEngine::cost($model, $variant, $mode), $model->modes())),
            'features' => ['ante' => false, 'buy' => $config['buy']],
            'tables' => [
                'base' => array_map($letters, $variant->base),
                'buy' => array_map($letters, $variant->buy),
                'free' => array_map($letters, $variant->free),
                'base_wilds' => ($variant->baseMultipliers ?? $variant->multipliers)->weights(),
                'free_wilds' => $variant->multipliers->weights(),
                'buy_scatters' => $variant->buyScatters->weights(),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:'.implode(',', SlotEngine::MODES)],
            'variant' => ['nullable', 'string', 'max:32'],
            'max_win_x' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    public function verify(Game $game, string $serverSeed, string $clientSeed, int $nonce, array $params): array
    {
        $config = app(SlotEngine::class)->validateConfig($game, (array) $game->config);
        $model = SlotModels::get($config['model']);
        $variant = isset($params['variant']) && $params['variant'] !== '' ? (string) $params['variant'] : $config['variant'];
        if (! isset($model->variants[$variant])) {
            throw ValidationException::withMessages(['variant' => "Model [{$model->code}] has no variant [{$variant}]."]);
        }
        // The outcome shows it as "5000.00".
        $maxWinX = isset($params['max_win_x']) && $params['max_win_x'] !== '' ? (int) floor((float) $params['max_win_x']) : $config['max_win_x'];
        if (! isset($model->maxWinChoices($model->variants[$variant])[$maxWinX * 100])) {
            throw ValidationException::withMessages(['max_win_x' => "Variant [{$variant}] was never played with a max win of {$maxWinX}×."]);
        }

        if (! in_array((string) $params['mode'], $model->modes(), true)) {
            throw ValidationException::withMessages(['mode' => "Model [{$model->code}] has no mode [{$params['mode']}]."]);
        }

        $result = SlotEngine::resolve($model->code, $variant, (string) $params['mode'], (new RandomStream($serverSeed, $clientSeed, $nonce))->nextInt(...), $maxWinX);

        return [
            'algorithm' => $model instanceof LinesModel ? self::LINES_ALGORITHM : self::ALGORITHM,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'model' => $model->code,
            'variant' => $variant,
            'max_win_x' => $maxWinX,
            'mode' => (string) $params['mode'],
            'total_win_x' => $result->outcome['total_win_x'],
            'free_spins' => $result->outcome['free_spins'],
            'win' => $result->won,
            'payout_multiplier' => $result->outcome['payout_multiplier'],
            'outcome' => $result->outcome,
        ];
    }
}
