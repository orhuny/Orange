<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameCategory;
use App\Domain\Launch\Models\PlaySession;
use Illuminate\Support\Facades\Cache;

/** What a player's lobby shows: the operator's games as tiles, by category, plus recent and popular. */
final class LobbyService
{
    private const RECENT = 10;

    private const POPULAR = 10;

    public function __construct(private readonly GameCatalogue $catalogue) {}

    /** @return array<string, mixed> */
    public function forSession(PlaySession $session): array
    {
        $frontends = (array) config('games.frontends', []);
        $tiles = collect($this->catalogue->forOperator($session->operator))
            // The lobby plays games directly: only those set up for direct play are offered.
            ->filter(fn (array $game): bool => isset($frontends[$game['type']]) && $game['play_mode'] === 'direct')
            ->map(fn (array $game): array => [
                'code' => $game['code'],
                'name' => $game['name'],
                'type' => $game['type'],
                'category' => $game['category'],
                'thumbnail_url' => $game['thumbnail_url'],
                'rtp' => $game['rtp'],
                'playable' => $game['status'] === 'active',
                'frame_url' => url((string) $frontends[$game['type']]).'?game='.rawurlencode($game['code']),
            ])
            ->values();
        $codes = $tiles->pluck('code')->all();

        $categories = GameCategory::query()
            ->where('is_active', true)
            ->whereIn('slug', $tiles->pluck('category')->filter()->unique()->all())
            ->orderBy('sort_order')
            ->get(['slug', 'name'])
            ->map(fn (GameCategory $c): array => ['slug' => $c->slug, 'name' => $c->name])
            ->all();

        return [
            'categories' => $categories,
            'games' => $tiles->all(),
            'recent' => array_values(array_intersect($this->recent($session), $codes)),
            'popular' => array_values(array_intersect($this->popular($session), $codes)),
        ];
    }

    /** @return list<string> the player's last distinct games, newest first */
    private function recent(PlaySession $session): array
    {
        $gameIds = Bet::query()
            ->where('operator_id', $session->operator_id)
            ->where('player_id', $session->player_id)
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('game_id')
            ->unique()
            ->take(self::RECENT)
            ->values();

        return $this->codes($gameIds->all());
    }

    /** @return list<string> the operator's most played games over the last week (cached 10 min) */
    private function popular(PlaySession $session): array
    {
        return Cache::remember("lobby.popular.{$session->operator_id}", 600, function () use ($session): array {
            $gameIds = Bet::query()
                ->where('operator_id', $session->operator_id)
                ->where('placed_at', '>=', now()->subDays(7))
                ->selectRaw('game_id, count(*) as plays')
                ->groupBy('game_id')
                ->orderByDesc('plays')
                ->limit(self::POPULAR)
                ->pluck('game_id');

            return $this->codes($gameIds->all());
        });
    }

    /**
     * @param  array<int, mixed>  $gameIds
     * @return list<string> codes, in the order of $gameIds
     */
    private function codes(array $gameIds): array
    {
        $codes = Game::query()->whereIn('id', $gameIds)->pluck('code', 'id');

        return array_values(array_filter(array_map(fn (mixed $id): ?string => $codes[$id] ?? null, $gameIds)));
    }
}
