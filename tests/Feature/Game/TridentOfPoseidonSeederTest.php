<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use Database\Seeders\TridentOfPoseidonSeeder;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\seed;

it('creates Trident of Poseidon as the panel would, with Sugar Orchard\'s currencies and its thumbnail', function (): void {
    Storage::fake('public');
    $orchard = Game::factory()->create(['code' => 'sugar-orchard', 'slug' => 'sugar-orchard', 'type' => 'slot', 'rtp_target' => 0.965, 'house_edge' => 0.035,
        'config' => ['model' => 'sugar-orchard', 'variant' => 'v1-9650']]);
    GameLimit::factory()->create(['game_id' => $orchard->id, 'currency' => 'TRY', 'min_bet' => '5', 'max_bet' => '20000', 'max_win' => '4000000']);

    seed(TridentOfPoseidonSeeder::class);

    $game = Game::query()->where('code', 'trident-of-poseidon')->sole();
    expect($game->getAttribute('access'))->toBe(GameAccess::Listed)
        ->and($game->isActive())->toBeTrue()
        ->and($game->config)->toMatchArray(['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'max_win_x' => 5000, 'theme' => 'trident-of-poseidon'])
        ->and((float) $game->rtp_target)->toBe(0.965)
        ->and($game->category?->slug)->toBe('slots')
        ->and(GameLimit::query()->where('game_id', $game->id)->pluck('currency')->all())->toBe(['TRY']);
    Storage::disk('public')->assertExists('thumbs/trident-of-poseidon.jpg');
});

it('leaves a game that already exists alone, so panel edits stay', function (): void {
    Storage::fake('public');
    seed(TridentOfPoseidonSeeder::class);
    $game = Game::query()->where('code', 'trident-of-poseidon')->sole();
    $game->update(['name' => 'Renamed in the panel']);
    expect(GameLimit::query()->where('game_id', $game->id)->pluck('currency')->all())->toBe(['EUR']);

    seed(TridentOfPoseidonSeeder::class);

    expect($game->fresh()->name)->toBe('Renamed in the panel')
        ->and(Game::query()->where('code', 'trident-of-poseidon')->count())->toBe(1);
});
