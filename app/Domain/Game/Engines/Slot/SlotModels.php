<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Models\StonesOfTheTitan;
use App\Domain\Game\Engines\Slot\Models\SugarOrchard;
use App\Domain\Game\Engines\Slot\Models\TridentOfPoseidon;
use App\Domain\Game\Engines\Slot\Models\TuttiFrutti;
use InvalidArgumentException;

/**
 * The slot maths models, by code (games.config.model). A new slot's maths is one line here.
 * Two kinds: tumbling "pay anywhere" models (TumbleModel) and paylines models (LinesModel).
 */
final class SlotModels
{
    /** @var array<string, class-string> code => class with a static model(): TumbleModel|LinesModel */
    private const MODELS = [
        SugarOrchard::CODE => SugarOrchard::class,
        TridentOfPoseidon::CODE => TridentOfPoseidon::class,
        StonesOfTheTitan::CODE => StonesOfTheTitan::class,
        TuttiFrutti::CODE => TuttiFrutti::class,
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::MODELS);
    }

    public static function has(string $code): bool
    {
        return isset(self::MODELS[$code]);
    }

    public static function get(string $code): TumbleModel|LinesModel
    {
        $class = self::MODELS[$code] ?? throw new InvalidArgumentException("Unknown slot model [{$code}].");

        /** @var TumbleModel|LinesModel */
        return $class::model();
    }
}
