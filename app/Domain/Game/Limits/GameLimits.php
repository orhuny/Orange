<?php

declare(strict_types=1);

namespace App\Domain\Game\Limits;

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Exceptions\StakeOutsideLimits;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Models\OperatorGameLimit;
use App\Domain\Operator\Models\Operator;

/**
 * Whether an operator's players may play a game, and with which limits.
 *
 * - The game must be active, and open to the operator: access=all unless an
 *   operator_game row disables it, access=listed only with an enabled row.
 * - A game with no game_limits rows accepts any currency without limits (crash,
 *   until rows are added); otherwise a currency needs its own row.
 * - Operator limits can only tighten: the stricter value of the two always wins.
 */
final class GameLimits
{
    /**
     * @throws GameNotAvailable
     * @throws CurrencyNotSupported
     */
    public function for(Game $game, Operator $operator, string $currency): EffectiveLimits
    {
        $currency = strtoupper($currency);
        $operatorGame = OperatorGame::query()
            ->where('operator_id', $operator->id)
            ->where('game_id', $game->id)
            ->first();

        $this->assertPlayable($game, $operatorGame);

        $gameLimits = GameLimit::query()->where('game_id', $game->id)->get();
        $limit = $gameLimits->firstWhere('currency', $currency);

        if ($gameLimits->isNotEmpty() && ! $limit instanceof GameLimit) {
            throw CurrencyNotSupported::for($game, $currency);
        }

        $own = $operatorGame === null ? null : OperatorGameLimit::query()
            ->where('operator_game_id', $operatorGame->id)
            ->where('currency', $currency)
            ->first();

        return self::combine($currency, $limit, $own);
    }

    /** The game's limits for a currency tightened by the operator's (either may be absent). */
    public static function combine(string $currency, ?GameLimit $limit, ?OperatorGameLimit $own): EffectiveLimits
    {
        return new EffectiveLimits(
            currency: $currency,
            minBet: self::higher(self::decimal($limit?->min_bet), self::decimal($own?->min_bet)),
            maxBet: self::lower(self::decimal($limit?->max_bet), self::decimal($own?->max_bet)),
            maxWin: self::lower(self::decimal($limit?->max_win), self::decimal($own?->max_win)),
        );
    }

    /** Whether the game is open to the operator (ignoring its status). */
    public static function isEnabledFor(Game $game, ?OperatorGame $operatorGame): bool
    {
        return $game->getAttribute('access') === GameAccess::All
            ? $operatorGame === null || $operatorGame->enabled
            : $operatorGame !== null && $operatorGame->enabled;
    }

    /** @throws StakeOutsideLimits */
    public function assertStake(EffectiveLimits $limits, string $amount): void
    {
        if ($limits->minBet !== null && bccomp($amount, $limits->minBet, 8) < 0) {
            throw StakeOutsideLimits::belowMin($limits);
        }

        if ($limits->maxBet !== null && bccomp($amount, $limits->maxBet, 8) > 0) {
            throw StakeOutsideLimits::aboveMax($limits);
        }
    }

    /** @throws GameNotAvailable */
    private function assertPlayable(Game $game, ?OperatorGame $operatorGame): void
    {
        if (! $game->isActive()) {
            throw GameNotAvailable::unavailable($game);
        }

        if (! self::isEnabledFor($game, $operatorGame)) {
            throw GameNotAvailable::notEnabled($game);
        }
    }

    private static function decimal(mixed $value): ?string
    {
        return $value === null ? null : bcadd((string) $value, '0', 8);
    }

    private static function higher(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return bccomp($a, $b, 8) >= 0 ? $a : $b;
    }

    private static function lower(?string $a, ?string $b): ?string
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return bccomp($a, $b, 8) <= 0 ? $a : $b;
    }
}
