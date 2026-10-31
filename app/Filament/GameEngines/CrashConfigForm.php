<?php

declare(strict_types=1);

namespace App\Filament\GameEngines;

use Filament\Schemas\Components\Text;

/**
 * Crash has no engine settings of its own yet (timings come from config/crash.php), so
 * there are no fields: a field bound to the whole `config` would also, while hidden for
 * another type, drop that type's settings when the form is saved.
 */
final class CrashConfigForm implements EngineConfigForm
{
    public static function components(): array
    {
        return [
            Text::make('Crash has no engine settings: growth rate and round timings come from config/crash.php.'),
        ];
    }
}
