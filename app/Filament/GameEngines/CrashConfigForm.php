<?php

declare(strict_types=1);

namespace App\Filament\GameEngines;

use App\Domain\Game\Engines\Crash\CrashEngine;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Text;

/**
 * Crash's one engine setting is the scene the game is played in (config('crash.scenes')).
 * Growth rate and round timings come from config/crash.php. The field is bound to
 * `config.scene`, not to `config`, so a hidden form never drops another type's settings.
 */
final class CrashConfigForm implements EngineConfigForm
{
    public static function components(): array
    {
        return [
            Select::make('config.scene')
                ->label('Scene')
                ->options(CrashEngine::scenes())
                ->placeholder('Deep Dive (default)')
                ->helperText('How the game looks: its art, sounds and words. The rounds and odds are the same in every scene.'),
            Text::make('Growth rate and round timings come from config/crash.php.'),
        ];
    }
}
