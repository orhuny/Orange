<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Exceptions\SeedInUse;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use Illuminate\Support\Facades\DB;

/**
 * Each player's seed pair for instant games: one active pair, shared by every instant
 * game; each bet takes the next nonce. Rotating reveals the server seed (so the player
 * can verify every bet on it) and starts a new pair.
 */
final class PlayerSeedService
{
    public function active(Player $player): PlayerSeed
    {
        $seed = PlayerSeed::query()->where('player_id', $player->id)->where('active', true)->first();

        return $seed instanceof PlayerSeed ? $seed : $this->mint($player, self::randomClientSeed());
    }

    /**
     * Takes the next nonce on the player's active pair. Call inside a transaction: the
     * pair is locked so two bets never share a nonce.
     *
     * @return array{0: PlayerSeed, 1: int}
     */
    public function nextNonce(Player $player): array
    {
        $this->active($player);

        /** @var PlayerSeed $seed */
        $seed = PlayerSeed::query()
            ->where('player_id', $player->id)
            ->where('active', true)
            ->lockForUpdate()
            ->firstOrFail();

        $nonce = $seed->nonce + 1;
        $seed->forceFill(['nonce' => $nonce])->save();

        return [$seed, $nonce];
    }

    /**
     * Reveals the active pair and starts a new one, with the given client seed or a fresh one.
     *
     * @return array{0: PlayerSeed, 1: PlayerSeed} the revealed pair and the new one
     *
     * @throws SeedInUse while a bet on the active pair is unresolved
     */
    public function rotate(Player $player, ?string $clientSeed = null): array
    {
        return DB::transaction(function () use ($player, $clientSeed): array {
            $current = $this->active($player);
            $current = PlayerSeed::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();

            $unresolved = Bet::query()
                ->where('player_seed_id', $current->id)
                ->where('status', BetStatus::Pending->value)
                ->exists();
            if ($unresolved) {
                throw new SeedInUse('A bet on this seed pair is still being settled; rotate once it has.');
            }

            $current->forceFill(['active' => false, 'revealed_at' => now()])->save();

            return [$current, $this->mint($player, $clientSeed ?? self::randomClientSeed())];
        });
    }

    private function mint(Player $player, string $clientSeed): PlayerSeed
    {
        $serverSeed = bin2hex(random_bytes(32));

        return PlayerSeed::create([
            'operator_id' => $player->operator_id,
            'player_id' => $player->id,
            'server_seed' => $serverSeed,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'client_seed' => $clientSeed,
            'nonce' => 0,
            'active' => true,
        ]);
    }

    private static function randomClientSeed(): string
    {
        return bin2hex(random_bytes(8));
    }
}
