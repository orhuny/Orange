<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * GET /crash — the page every crash-type game plays in, embedded by Orange's host pages
 * (?game={code}) or by an operator page. Each game has its own rounds, so the history and
 * the live bets are that game's, and its scene (games.config.scene) decides how it looks.
 */
class CrashPageController
{
    public function __invoke(Request $request): View
    {
        // The game being played (?game={code}, set by Orange's host pages); an operator
        // embedding the page without one (or with an unknown one) gets the first crash game.
        $games = Game::query()->where('type', 'crash')->where('status', '!=', 'draft');
        $code = $request->query('game');
        $game = (is_string($code) && $code !== '' ? (clone $games)->where('code', $code)->first() : null)
            ?? $games->orderBy('id')->first();

        $rounds = fn () => GameRound::query()->when($game instanceof Game, fn ($q) => $q->where('game_id', $game?->getKey()));

        // Only finished rounds: crash_point is stored at round start, so the live
        // round must never show up here.
        $recentCrashes = $rounds()
            ->whereIn('state', GameRound::CRASH_POINT_PUBLIC_STATES)
            ->whereNotNull('crash_point')
            ->latest('id')
            ->limit(8)
            ->get(['round_uuid', 'crash_point'])
            // Each chip opens its round's details (GET /v1/rounds/{uuid}).
            ->map(fn (GameRound $round) => ['round_uuid' => $round->round_uuid, 'crash_point' => (float) $round->crash_point])
            ->values()
            ->all();

        // The live bet list starts from these: the round in play (if any) and the last one over.
        $liveRounds = config('crash.live_bets', true) ? [
            'current' => $rounds()->where('state', '!=', RoundState::Settled)->latest('id')->value('round_uuid'),
            'previous' => $rounds()->where('state', RoundState::Settled)->latest('id')->value('round_uuid'),
        ] : null;

        return view('crash', [
            'game' => $game,
            'scene' => $game instanceof Game ? CrashEngine::sceneOf($game) : CrashEngine::DEFAULT_SCENE,
            // Rounds go out on game.{code}; with no crash game at all, the legacy channel.
            'roundChannel' => $game instanceof Game ? "game.{$game->code}" : 'crash',
            'recentCrashes' => $recentCrashes,
            'liveRounds' => $liveRounds,
            'rtp' => $game instanceof Game ? number_format((float) $game->rtp_target, 4, '.', '') : null,
        ]);
    }
}
