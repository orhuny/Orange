<?php

declare(strict_types=1);

namespace App\Domain\Game\Limits;

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replaces an operator's own per-currency limits for a game. They may only tighten the
 * game's limits: a higher minimum, a lower maximum or max win, and only in currencies
 * the game takes. (GameLimits applies the stricter value anyway; this refuses looser
 * input so what is stored is what applies.)
 */
final class OperatorLimitsEditor
{
    /**
     * @param  list<array{currency?: mixed, min_bet?: mixed, max_bet?: mixed, max_win?: mixed}>  $rows
     *
     * @throws InvalidArgumentException with a message fit to show the user
     */
    public function replace(string $operatorId, Game $game, array $rows): void
    {
        $gameLimits = GameLimit::query()->where('game_id', $game->id)->get()->keyBy('currency');
        $clean = [];

        foreach ($rows as $row) {
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('Each row needs a 3-letter currency code.');
            }
            if (isset($clean[$currency])) {
                throw new InvalidArgumentException("{$currency} is listed twice.");
            }

            $values = [];
            foreach (['min_bet', 'max_bet', 'max_win'] as $field) {
                $value = $row[$field] ?? null;
                $value = $value === null || $value === '' ? null : trim((string) $value);
                if ($value !== null && ! preg_match('/^\d{1,10}(\.\d{1,8})?$/', $value)) {
                    throw new InvalidArgumentException("{$currency}: {$field} must be a plain decimal.");
                }
                $values[$field] = $value === null ? null : bcadd($value, '0', 8);
            }

            if ($values['min_bet'] !== null && $values['max_bet'] !== null && bccomp($values['min_bet'], $values['max_bet'], 8) > 0) {
                throw new InvalidArgumentException("{$currency}: the minimum bet is above the maximum bet.");
            }

            $limit = $gameLimits->get($currency);
            if ($gameLimits->isNotEmpty() && ! $limit instanceof GameLimit) {
                throw new InvalidArgumentException("{$game->name} doesn't take {$currency}.");
            }
            if ($limit instanceof GameLimit) {
                $this->assertTighter($currency, $values, $limit);
            }

            // The max win must stay above the largest stake, or a win could pay less than it cost.
            $maxBet = $values['max_bet'] ?? ($limit instanceof GameLimit ? (string) $limit->max_bet : null);
            $maxWin = $values['max_win'] ?? ($limit instanceof GameLimit ? (string) $limit->max_win : null);
            if ($maxBet !== null && $maxWin !== null && bccomp($maxWin, $maxBet, 8) <= 0) {
                throw new InvalidArgumentException("{$currency}: the max win must be above the max bet ({$maxBet}).");
            }

            $clean[$currency] = $values;
        }

        DB::transaction(function () use ($operatorId, $game, $clean): void {
            $operatorGame = OperatorGame::query()->where('operator_id', $operatorId)->where('game_id', $game->id)->first();
            if (! $operatorGame instanceof OperatorGame) {
                // Setting limits must never be what enables a game for an operator.
                if ($game->getAttribute('access') !== GameAccess::All) {
                    throw new InvalidArgumentException("{$game->name} isn't enabled for this operator.");
                }
                $operatorGame = OperatorGame::create(['operator_id' => $operatorId, 'game_id' => $game->id, 'enabled' => true]);
            }

            $operatorGame->limits()->delete();
            foreach ($clean as $currency => $values) {
                $operatorGame->limits()->create(['currency' => $currency] + $values);
            }
        });
    }

    /** @param  array{min_bet: string|null, max_bet: string|null, max_win: string|null}  $values */
    private function assertTighter(string $currency, array $values, GameLimit $limit): void
    {
        $checks = [
            ['min_bet', fn (string $v) => bccomp($v, (string) $limit->min_bet, 8) >= 0, 'at least the game minimum', $limit->min_bet],
            ['max_bet', fn (string $v) => bccomp($v, (string) $limit->max_bet, 8) <= 0, 'at most the game maximum', $limit->max_bet],
            ['max_win', fn (string $v) => bccomp($v, (string) $limit->max_win, 8) <= 0, 'at most the game max win', $limit->max_win],
        ];

        foreach ($checks as [$field, $ok, $rule, $bound]) {
            if ($values[$field] !== null && ! $ok($values[$field])) {
                throw new InvalidArgumentException("{$currency}: {$field} must be {$rule} ({$bound}). Limits can only be tightened.");
            }
        }
    }
}
