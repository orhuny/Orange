<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\Pages;

use App\Domain\Game\Models\Game;
use App\Filament\Resources\GameResource;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Game $game */
        $game = $this->getRecord();

        return GameResource::validateEngineConfig($data, $game);
    }
}
