<?php

declare(strict_types=1);

namespace App\Filament\GameEngines;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * The admin form for one engine's games.config, registered per type in
 * config('games.admin_forms'). Fields bind to config.* (e.g. config.min_chance).
 */
interface EngineConfigForm
{
    /** @return array<int, Component|Field> */
    public static function components(): array;
}
