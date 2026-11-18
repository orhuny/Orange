<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot;

use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Models\SugarOrchard;
use InvalidArgumentException;

/** The slot maths models, by code (games.config.model). A new slot's maths is one line here. */
final class SlotModels
{
    /** @var array<string, class-string> code => class with a static model(): TumbleModel */
    private const MODELS = [
        SugarOrchard::CODE => SugarOrchard::class,
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

    public static function get(string $code): TumbleModel
    {
        $class = self::MODELS[$code] ?? throw new InvalidArgumentException("Unknown slot model [{$code}].");

        /** @var TumbleModel */
        return $class::model();
    }
}
