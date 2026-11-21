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
                    ->placeholder(SlotModels::codes()[0])
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        // Another model: its own themes, and a variant and max win it has.
                        $set('config.theme', null);
                        if (! array_key_exists((string) $get('config.variant'), self::variants(self::modelCode($get)))) {
                            $set('config.variant', null);
                        }
                        self::fillRtp($set, $get);
                    }),
                Select::make('config.variant')->label('RTP variant')->live()
                    ->options(fn (Get $get): array => self::variants(self::modelCode($get)))
                    ->helperText('Sets the RTP target and house edge.')
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::fillRtp($set, $get)),
                Select::make('config.max_win_x')->label('Max win (× bet)')->live()
                    ->options(fn (Get $get): array => self::maxWins($get))
                    ->placeholder(fn (Get $get): string => self::defaultMaxWin($get))
                    ->helperText('The most one spin, with its free spins, can win. Each choice has its own measured RTP. The max win in the currency limits still applies.')
                    ->afterStateUpdated(fn (Set $set, Get $get) => self::fillRtp($set, $get)),
                Select::make('config.theme')->label('Theme')
                    ->options(fn (Get $get): array => self::themes(self::modelCode($get)))
                    ->placeholder(fn (Get $get): string => (string) (array_values(self::themes(self::modelCode($get)))[0] ?? '')),
            ]),
            Grid::make(3)->schema([
                Toggle::make('config.ante')->label('Ante bet (+25%)')->default(true),
                Toggle::make('config.buy')->label('Bonus buy (100×)')->default(true)
                    ->helperText('Switch off where buying the feature is not allowed.'),
                Toggle::make('config.super')->label('Super buy')->default(true)
                    ->visible(fn (Get $get): bool => self::superBuyTide($get) !== null)
                    ->helperText(fn (Get $get): string => 'Free spins that start with the multiplier at '.self::superBuyTide($get).'×. Needs a max win of at least '
                        .self::defaultMaxWin($get).'; switch off where buying the feature is not allowed.'),
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
        $code = self::modelCode($get);
        if (! SlotModels::has($code)) {
            return;
        }
        $model = SlotModels::get($code);
        $variant = $model->variants[(string) ($get('config.variant') ?: $model->defaultVariant)] ?? null;
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
        $code = self::modelCode($get);
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
        $code = self::modelCode($get);

        return SlotModels::has($code) ? number_format(intdiv(SlotModels::get($code)->maxWin, 100)).'× (default)' : '';
    }

    /** The tide a super buy starts with, or null when the picked model has no super buy. */
    private static function superBuyTide(Get $get): ?int
    {
        $code = self::modelCode($get);

        return SlotModels::has($code) ? SlotModels::get($code)->superBuyTide : null;
    }

    /** The picked maths model, or the first (the placeholder) when none is. */
    private static function modelCode(Get $get): string
    {
        return (string) ($get('config.model') ?: SlotModels::codes()[0]);
    }

    /** @return array<string, string> the model's themes: key => label */
    private static function themes(string $model): array
    {
        $themes = array_filter((array) config('slots.themes', []), fn (mixed $theme): bool => is_array($theme) && ($theme['model'] ?? null) === $model);

        return array_map(fn (array $theme): string => (string) ($theme['label'] ?? ''), $themes);
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
