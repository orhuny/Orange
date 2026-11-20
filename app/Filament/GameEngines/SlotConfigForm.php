<?php

declare(strict_types=1);

namespace App\Filament\GameEngines;

use App\Domain\Game\Engines\Slot\SlotModels;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Slots: the maths model, its RTP variant and the max win (together they set the game's RTP
 * target: a slot's RTP comes from its measured tables, so it can't be typed in), the theme,
 * the features a jurisdiction may forbid, and the bet steps the page offers.
 */
final class SlotConfigForm implements EngineConfigForm
{
    public static function components(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('config.model')->label('Maths model')->live()
                    ->options(fn (): array => array_combine(SlotModels::codes(), SlotModels::codes()))
                    ->placeholder(SlotModels::codes()[0]),
                Select::make('config.variant')->label('RTP variant')->live()
                    ->options(fn (Get $get): array => self::variants((string) ($get('config.model') ?: SlotModels::codes()[0])))
                    ->helperText('Sets the RTP target and house edge.')
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::fillRtp($set, $get)),
                Select::make('config.max_win_x')->label('Max win (× bet)')->live()
                    ->options(fn (Get $get): array => self::maxWins($get))
                    ->placeholder(fn (Get $get): string => self::defaultMaxWin($get))
                    ->helperText('The most one spin, with its free spins, can win. Each choice has its own measured RTP. The max win in the currency limits still applies.')
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::fillRtp($set, $get)),
                Select::make('config.theme')->label('Theme')
                    ->options(fn (): array => array_map(fn (array $theme): string => (string) $theme['label'], (array) config('slots.themes', [])))
                    ->placeholder((string) array_key_first((array) config('slots.themes', []))),
            ]),
            Grid::make(3)->schema([
                Toggle::make('config.ante')->label('Ante bet (+25%)')->default(true),
                Toggle::make('config.buy')->label('Bonus buy (100×)')->default(true)
                    ->helperText('Switch off where buying the feature is not allowed.'),
                Toggle::make('config.autoplay')->label('Autoplay')->default(true),
            ]),
            TagsInput::make('config.bet_steps')->label('Bet steps')
                ->placeholder('e.g. 0.20, then Enter')
                ->helperText('The stakes the − / + buttons step through (the game\'s limits still apply). Leave empty for the defaults.'),
        ];
    }

    /**
     * The RTP target and house edge of the picked variant and max win. A max win the new
     * variant wasn't measured with falls back to the model's default.
     */
    private static function fillRtp(Set $set, Get $get): void
    {
        $model = SlotModels::get((string) ($get('config.model') ?: SlotModels::codes()[0]));
        $variant = $model->variants[(string) $get('config.variant')] ?? null;
        if ($variant === null) {
            return;
        }
        $choices = $model->maxWinChoices($variant);
        $picked = $get('config.max_win_x');
        $cap = is_numeric($picked) ? (int) $picked * 100 : $model->maxWin;
        if (! isset($choices[$cap])) {
            $set('config.max_win_x', null);
            $cap = $model->maxWin;
        }
        $set('rtp_target', $choices[$cap]);
        $set('house_edge', bcsub('1', $choices[$cap], 4));
    }

    /** @return array<string, string> max win (× bet) => label with its RTP */
    private static function maxWins(Get $get): array
    {
        $code = (string) ($get('config.model') ?: SlotModels::codes()[0]);
        if (! SlotModels::has($code)) {
            return [];
        }
        $model = SlotModels::get($code);
        $variant = $model->variants[(string) ($get('config.variant') ?: $model->defaultVariant)] ?? null;
        if ($variant === null) {
            return [];
        }

        $options = [];
        foreach ($model->maxWinChoices($variant) as $cap => $rtp) {
            $options[(string) intdiv($cap, 100)] = number_format(intdiv($cap, 100)).'× · RTP '.number_format((float) $rtp * 100, 2).'%';
        }

        return $options;
    }

    private static function defaultMaxWin(Get $get): string
    {
        $code = (string) ($get('config.model') ?: SlotModels::codes()[0]);

        return SlotModels::has($code) ? number_format(intdiv(SlotModels::get($code)->maxWin, 100)).'× (default)' : '';
    }

    /** @return array<string, string> variant id => label */
    private static function variants(string $model): array
    {
        if (! SlotModels::has($model)) {
            return [];
        }

        $options = [];
        foreach (SlotModels::get($model)->variants as $id => $variant) {
            $options[$id] = $id.' · RTP '.number_format((float) $variant->rtp * 100, 2).'%';
        }

        return $options;
    }
}
