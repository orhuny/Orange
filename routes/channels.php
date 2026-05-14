<?php

declare(strict_types=1);

use App\Domain\Game\Models\GameRound;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('round.{roundUuid}', function ($user, string $roundUuid): bool {
    return GameRound::query()->where('round_uuid', $roundUuid)->exists();
});

Broadcast::channel('crash', function (): bool {
    return true;
});
