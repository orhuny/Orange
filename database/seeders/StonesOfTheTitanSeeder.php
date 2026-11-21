<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Game\Engines\Slot\SlotEngine;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameCategory;
use App\Domain\Game\Models\GameLimit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Creates Stones of the Titan, the third slot, as if made in the admin panel (Games → New):
 * active, listed (players see it only where an operator has it enabled), RTP variant v1-9650
 * with the default 5,000× max win (the Super Eclipse buy on), the "slots" lobby category and the
 * lobby thumbnail.
 *
 * Limits: the same currencies and amounts as Sugar Orchard if it has any, otherwise an EUR
 * placeholder. A slot only accepts the currencies it has a limits row for.
 *
 * Safe to run again: it never changes a game that already exists, so edits made in the panel
 * stay. Then, in the panel: enable the game for an operator (Operators → the operator → Games),
 * with play mode "direct" for the lobby.
 *
 *   php artisan db:seed --class=StonesOfTheTitanSeeder            (add --force in production)
 */
class StonesOfTheTitanSeeder extends Seeder
{
    public const CODE = 'stones-of-the-titan';

    public function run(): void
    {
        if (Game::query()->where('code', self::CODE)->exists()) {
            $this->command->info('Stones of the Titan already exists: left as it is.');

            return;
        }

        $slots = GameCategory::query()->firstOrCreate(['slug' => 'slots'], ['name' => 'Slots', 'sort_order' => 30]);
        $game = (new Game)->forceFill([
            'code' => self::CODE,
            'slug' => self::CODE,
            'type' => 'slot',
            'access' => GameAccess::Listed,
            'category_id' => $slots->id,
            'name' => 'Stones of the Titan',
            'version' => 1,
            'rtp_target' => 0.965,
            'house_edge' => 0.035,
            'status' => 'active',
        ]);
        // The engine fills in every default (ante, buy, autoplay, bet steps, max win) as the panel would.
        $game->setAttribute('config', app(SlotEngine::class)->validateConfig($game, ['model' => self::CODE, 'variant' => 'v1-9650', 'theme' => self::CODE]));
        $game->setAttribute('thumbnail', $this->thumbnail());
        $game->save();

        $limits = $this->limits($game);
        $this->command->info('Created Stones of the Titan (#'.$game->id.'), limits for '.implode(', ', $limits).'.');
        $this->command->line('Next, in the admin panel: enable it for your operator (play mode "direct" for the lobby), and check its limits per currency.');
    }

    /** The lobby tile, copied to the public disk as if uploaded in the panel (null if the art isn't there). */
    private function thumbnail(): ?string
    {
        $source = public_path('images/'.self::CODE.'/thumbnail.jpg');
        if (! is_file($source)) {
            $this->command->warn('No public/images/'.self::CODE.'/thumbnail.jpg: upload a thumbnail in the panel.');

            return null;
        }
        $path = 'thumbs/'.self::CODE.'.jpg';
        Storage::disk('public')->put($path, (string) file_get_contents($source));

        return $path;
    }

    /**
     * Sugar Orchard's limits (same currencies, same amounts), or EUR placeholders.
     *
     * @return list<string> the currencies
     */
    private function limits(Game $game): array
    {
        $orchard = Game::query()->where('code', 'sugar-orchard')->first();
        $rows = $orchard === null ? collect() : GameLimit::query()->where('game_id', $orchard->id)->get();
        if ($rows->isEmpty()) {
            GameLimit::query()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.20', 'max_bet' => '500.00', 'max_win' => '100000.00']);

            return ['EUR (placeholder amounts)'];
        }
        foreach ($rows as $row) {
            GameLimit::query()->create(['game_id' => $game->id] + $row->only(['currency', 'min_bet', 'max_bet', 'max_win']));
        }

        return $rows->pluck('currency')->values()->all();
    }
}
