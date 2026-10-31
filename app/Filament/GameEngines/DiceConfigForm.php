<?php

declare(strict_types=1);

namespace App\Filament\GameEngines;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/** Dice: the range of win chances players may pick (the multiplier follows from the chance and RTP). */
final class DiceConfigForm implements EngineConfigForm
{
    public static function components(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('config.min_chance')->label('Lowest win chance (%)')->required()->placeholder('0.01')
                    ->regex('/^\d{1,2}(\.\d{1,2})?$/'),
                TextInput::make('config.max_chance')->label('Highest win chance (%)')->required()->placeholder('98.00')
                    ->regex('/^\d{1,2}(\.\d{1,2})?$/'),
            ]),
        ];
    }
}
