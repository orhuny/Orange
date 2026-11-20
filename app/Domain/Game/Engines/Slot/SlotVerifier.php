<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Contracts\FairnessVerifier;
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

    public function describe(Game $game): array
    {
        $config = app(SlotEngine::class)->validateConfig($game, (array) $game->config);
        $model = SlotModels::get($config['model']);
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
                'mode' => 'base, ante or buy (in the bet\'s outcome)',
                'variant' => 'the RTP variant the bet was played with (in its outcome); defaults to the game\'s current one',
                'max_win_x' => 'the max win the bet was played with (in its outcome); defaults to the game\'s current one',
            ],
            'formula' => 'nextInt(n): read unsigned 32-bit big-endian integers from block_k = HMAC_SHA256(key=server_seed, msg="{client_seed}:{nonce}:{k}"), '
                .'k = 0, 1, …, eight per block; take the first u < floor(2^32 / n) * n, then u mod n. '
                .'A weighted draw calls nextInt(total) once and lands on the first entry (in the listed order) whose running total exceeds it. '
                ."The grid is {$model->cols} columns × {$model->rows} rows; cells are drawn column 0 first, each column top to bottom, "
                .'from that column\'s table ("base", "ante", or in free spins "free"); a multiplier cell (M) draws its value from "multipliers" right after it. '
                .'A bought spin first draws the scatter count from "buy_scatters", then picks their cells by a partial Fisher–Yates shuffle '
                ."of 0…{$model->cells}-1 (for i = 0…count-1: swap i with i + nextInt({$model->cells} - i); cell = column × {$model->rows} + row), "
                .'then draws every other cell in order from "buy". '
                ."Every pay symbol with at least {$model->payTiers[0]} copies anywhere pays its tier; they are removed, the rest fall, and the gaps refill "
                .'(columns left to right, new cells top to bottom, same draws); repeat until nothing pays. '
                ."Scatters (S) are counted on the final grid: {$model->freeSpinsTriggerAt}+ pay the scatter pay and award {$model->freeSpinsAwarded} free spins; "
                ."in free spins {$model->retriggerAt}+ add {$model->retriggerAwarded} (at most {$model->maxFreeSpins} in all). "
                .$multipliers
                .'A bet\'s total win stops at max_win and the feature ends there. Wins are multiples of the bet; '
                .'payout_multiplier = floor4(total win / cost), cost = 1 (base), 1.25 (ante) or 100 (buy).',
            'grid' => ['columns' => $model->cols, 'rows' => $model->rows],
            'symbols' => array_combine($model->symbols, $model->symbolNames),
            'paytable' => $paytable,
            'scatter_pays' => array_map(fn (int $h): string => TumbleModel::x($h), $model->scatterPays),
            'free_spins' => [
                'trigger_scatters' => $model->freeSpinsTriggerAt, 'awarded' => $model->freeSpinsAwarded,
                'retrigger_scatters' => $model->retriggerAt, 'retrigger_awarded' => $model->retriggerAwarded, 'max' => $model->maxFreeSpins,
            ],
            'max_win' => TumbleModel::x($cap),
            'multipliers' => ['base_game' => $model->baseMultipliers, 'free_spins' => $model->freeSpinMultiplier],
            'costs' => array_combine(SlotEngine::MODES, array_map(fn (string $mode): string => SlotEngine::cost($mode), SlotEngine::MODES)),
            'features' => ['ante' => $config['ante'], 'buy' => $config['buy']],
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

        $result = SlotEngine::resolve($model->code, $variant, (string) $params['mode'], (new RandomStream($serverSeed, $clientSeed, $nonce))->nextInt(...), $maxWinX);

        return [
            'algorithm' => self::ALGORITHM,
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
