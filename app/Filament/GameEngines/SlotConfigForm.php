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
 * Slots: the maths model and its RTP variant (which sets the game's RTP target: a slot's
 * RTP comes from its measured tables, so it can't be typed in), the theme, the features a
 * jurisdiction may forbid, and the bet steps the page offers.
 */
final class SlotConfigForm implements EngineConfigForm
{
    public static function components(): array
    {
        return [
            Grid::make(3)->schema([
                Select::make('config.model')->label('Maths model')->live()
                    ->options(fn (): array => array_combine(SlotModels::codes(), SlotModels::codes()))
                    ->placeholder(SlotModels::codes()[0]),
                Select::make('config.variant')->label('RTP variant')->live()
                    ->options(fn (Get $get): array => self::variants((string) ($get('config.model') ?: SlotModels::codes()[0])))
                    ->helperText('Sets the RTP target and house edge.')
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                        $model = SlotModels::get((string) ($get('config.model') ?: SlotModels::codes()[0]));
                        if ($state !== null && isset($model->variants[$state])) {
                            $rtp = $model->variants[$state]->rtp;
                            $set('rtp_target', $rtp);
                            $set('house_edge', bcsub('1', $rtp, 4));
                        }
                    }),
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
