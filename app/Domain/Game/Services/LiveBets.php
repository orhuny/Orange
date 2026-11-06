<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\RoundBetsUpdated;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\GameRound;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A round's live bet list, which the game page shows every player (like Aviator's "All
 * bets"): each bet's player (masked), stake and, once cashed out, multiplier and win.
 * Changes go out as round.bets; a page that joins late asks for the list
 * (GET /v1/rounds/{uuid}/bets).
 */
final class LiveBets
{
    /** The bets that count: not stakes declined, rolled back or still being debited. */
    public const STATUSES = [BetStatus::Placed, BetStatus::Won, BetStatus::Lost];

    /** Rows a round.bets message carries at most, so it stays well under Reverb's 10 kB request limit. */
    private const ROWS_PER_MESSAGE = 30;

    /** @var array<int, array{0: GameRound, 1: list<array<string, mixed>>}>|null rows held back while batching */
    private ?array $batch = null;

    /** @return array<string, mixed> */
    public function row(Bet $bet): array
    {
        $won = $bet->getAttribute('status') === BetStatus::Won;

        $player = $bet->player;

        return [
            'id' => $bet->id,
            // The operator's display name when a launch gave one (so its initial means
            // something), else its player id; masked either way.
            'player' => self::mask((string) ($player?->username ?: $player?->external_player_id)),
            'amount' => (string) $bet->amount,
            'currency' => $bet->currency,
            // Only what has happened: a cash-out. Nobody's auto-cashout target is shown.
            'multiplier' => $won ? (string) $bet->multiplier : null,
            'payout' => $won ? (string) $bet->payout_amount : null,
        ];
    }

    /**
     * Every bet that counts in the round, biggest stake first.
     *
     * @return list<array<string, mixed>>
     */
    public function forRound(GameRound $round): array
    {
        return Bet::query()
            ->with('player')
            ->where('round_id', $round->id)
            ->whereIn('status', array_map(fn (BetStatus $s): string => $s->value, self::STATUSES))
            ->orderByDesc('amount')
            ->orderBy('id')
            ->get()
            ->map(fn (Bet $bet): array => $this->row($bet))
            ->values()
            ->all();
    }

    /** Sends a bet's row once its transaction commits: at once, or with the rest of the batch. */
    public function publish(Bet $bet): void
    {
        $round = $bet->round;
        if (! $round instanceof GameRound || ! config('crash.live_bets', true)) {
            return;
        }
        $row = $this->row($bet);

        DB::afterCommit(function () use ($round, $row): void {
            if ($this->batch === null) {
                $this->send($round, [$row]);

                return;
            }
            $this->batch[$round->id][0] = $round;
            $this->batch[$round->id][1][] = $row;
        });
    }

    /**
     * Runs $work and sends the rows it publishes together, a message a round (the
     * auto-cashouts of one tick), instead of one message each.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function batch(callable $work): mixed
    {
        if ($this->batch !== null) {
            return $work(); // the outer batch sends them
        }

        $this->batch = [];
        try {
            return $work();
        } finally {
            /** @var array<int, array{0: GameRound, 1: list<array<string, mixed>>}> $batch filled by publish() during $work */
            $batch = $this->batch ?? [];
            $this->batch = null;
            foreach ($batch as [$round, $rows]) {
                $this->send($round, $rows);
            }
        }
    }

    /** "1***5": enough for players to recognise themselves, not to identify anyone. */
    public static function mask(string $playerId): string
    {
        $id = trim($playerId);

        return match (true) {
            $id === '' => '***',
            mb_strlen($id) <= 2 => mb_substr($id, 0, 1).'***',
            default => mb_substr($id, 0, 1).'***'.mb_substr($id, -1),
        };
    }

    /**
     * Never throws: this runs after the bet or cash-out has committed, so a broadcast that
     * fails (Reverb unreachable) must not turn its request, or the tick loop, into an error.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function send(GameRound $round, array $rows): void
    {
        foreach (array_chunk($rows, self::ROWS_PER_MESSAGE) as $chunk) {
            try {
                RoundBetsUpdated::dispatch($round, $chunk);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
