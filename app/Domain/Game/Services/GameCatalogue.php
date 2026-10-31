<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Models\OperatorGameLimit;
use App\Domain\Operator\Models\Operator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** The games an operator can offer, with what they need to list and launch them. */
final class GameCatalogue
{
    /** Statuses shown to operators; draft and disabled games are not listed. */
    private const LISTED_STATUSES = ['active', 'maintenance'];

    public function __construct(private readonly EngineRegistry $engines) {}

    /** @return list<array<string, mixed>> */
    public function forOperator(Operator $operator): array
    {
        $operatorGames = OperatorGame::query()
            ->where('operator_id', $operator->id)
            ->with('limits')
            ->get()
            ->keyBy('game_id');

        return Game::query()
            ->with(['category', 'limits'])
            ->whereIn('status', self::LISTED_STATUSES)
            ->get()
            ->filter(fn (Game $game): bool => $this->engines->has($game->engineType())
                && GameLimits::isEnabledFor($game, $operatorGames->get($game->id)))
            ->sortBy(fn (Game $game): array => [
                $game->category->sort_order ?? PHP_INT_MAX,
                $operatorGames->get($game->id)->sort_order ?? $game->sort_order,
                $game->name,
            ])
            ->map(fn (Game $game): array => $this->entry($game, $operatorGames->get($game->id)))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function entry(Game $game, ?OperatorGame $operatorGame): array
    {
        /** @var Collection<int, GameLimit> $gameLimits */
        $gameLimits = $game->limits;
        /** @var Collection<int, OperatorGameLimit> $ownLimits */
        $ownLimits = $operatorGame === null ? collect() : $operatorGame->limits;

        // A game with limit rows supports exactly those currencies; one without supports any,
        // with only the operator's own limits (if it set any) applying.
        $currencies = $gameLimits->isNotEmpty()
            ? $gameLimits->pluck('currency')
            : $ownLimits->pluck('currency');

        $limits = [];
        foreach ($currencies->unique()->sort()->values() as $currency) {
            $effective = GameLimits::combine(
                (string) $currency,
                $gameLimits->firstWhere('currency', $currency),
                $ownLimits->firstWhere('currency', $currency),
            );
            $limits[$currency] = [
                'min_bet' => $effective->minBet,
                'max_bet' => $effective->maxBet,
                'max_win' => $effective->maxWin,
            ];
        }

        $settings = $operatorGame?->getAttribute('settings');
        $playMode = is_array($settings) && is_string($settings['play_mode'] ?? null) ? $settings['play_mode'] : 'relay';

        return [
            'code' => $game->code,
            'name' => $game->name,
            'type' => $game->engineType(),
            'family' => $this->engines->forGame($game)->family()->value,
            'category' => $game->category?->slug,
            'thumbnail_url' => $game->thumbnail === null ? null : Storage::disk('public')->url($game->thumbnail),
            'status' => $game->status,
            'rtp' => number_format((float) $game->rtp_target, 4, '.', ''),
            'currencies' => $gameLimits->isNotEmpty() ? 'listed' : 'any',
            'limits' => (object) $limits,
            'play_mode' => $playMode,
        ];
    }
}
