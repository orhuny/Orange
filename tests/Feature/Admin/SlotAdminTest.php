<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;
use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Models\User;
use App\Support\AccessControl;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('offers slot as a game type, and picking an RTP variant sets the RTP target and house edge', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(CreateGame::class)
        ->fillForm(['type' => 'slot', 'code' => 'orchard-95', 'slug' => 'orchard-95', 'name' => 'Orchard 95', 'status' => 'draft', 'access' => 'listed'])
        ->assertFormFieldExists('config.variant')
        ->fillForm(['config.variant' => 'v1-9550'])
        ->assertFormSet(['rtp_target' => '0.9550', 'house_edge' => '0.0450'])
        ->fillForm(['config.buy' => false, 'config.bet_steps' => ['2', '0.5']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Game::query()->where('code', 'orchard-95')->sole()->config)->toBe([
        'model' => 'sugar-orchard', 'variant' => 'v1-9550', 'max_win_x' => 5000, 'theme' => 'sugar-orchard',
        'ante' => true, 'buy' => false, 'autoplay' => true, 'bet_steps' => ['0.50', '2.00'],
    ]);
});

it('picks Trident of Poseidon\'s max win, which sets the RTP, and gives it its own theme', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(CreateGame::class)
        ->fillForm(['type' => 'slot', 'code' => 'trident-1k', 'slug' => 'trident-1k', 'name' => 'Trident 1k', 'status' => 'draft', 'access' => 'listed'])
        ->fillForm(['config.model' => 'trident-of-poseidon'])
        ->fillForm(['config.variant' => 'v1-9650'])
        ->fillForm(['config.max_win_x' => '1000'])
        ->assertFormSet(['rtp_target' => '0.9169', 'house_edge' => '0.0831'])
        // Sugar Orchard has no 1,000× cap: switching to it resets the cap, the RTP and the theme.
        ->fillForm(['config.model' => 'sugar-orchard'])
        ->assertFormSet(['config.max_win_x' => null, 'config.theme' => null, 'rtp_target' => '0.9650'])
        ->fillForm(['config.model' => 'trident-of-poseidon'])
        ->fillForm(['config.max_win_x' => '10000'])
        ->assertFormSet(['rtp_target' => '0.9656', 'house_edge' => '0.0344'])
        ->call('create')
        ->assertHasNoFormErrors();

    // Bet steps left empty, as the form says: the defaults.
    expect(Game::query()->where('code', 'trident-1k')->sole()->config)->toMatchArray([
        'model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'max_win_x' => 10000, 'theme' => 'trident-of-poseidon',
        'bet_steps' => ['0.20', '0.40', '0.60', '0.80', '1.00', '2.00', '3.00', '4.00', '5.00', '10.00', '20.00', '50.00', '100.00'],
    ]);
});

it('refuses a slot whose RTP target does not match its variant', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());

    Livewire::test(CreateGame::class)
        ->fillForm(['type' => 'slot', 'code' => 'orchard-x', 'slug' => 'orchard-x', 'name' => 'Orchard X', 'status' => 'draft', 'access' => 'listed',
            'config' => ['variant' => 'v1-9650']])
        ->fillForm(['rtp_target' => 0.99, 'house_edge' => 0.01])
        ->call('create')
        ->assertHasErrors(['data.config']);

    expect(Game::query()->where('code', 'orchard-x')->exists())->toBeFalse();
});

it('edits a slot\'s features without touching its other settings', function (): void {
    actingAs(User::factory()->withRole(AccessControl::SUPER_ADMIN)->create());
    $game = Game::factory()->create(['code' => 'orchard', 'slug' => 'orchard', 'type' => 'slot', 'rtp_target' => 0.945, 'house_edge' => 0.055,
        'config' => ['variant' => 'v1-9450', 'bet_steps' => ['1', '2']]]);

    Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
        ->assertFormSet(['config.variant' => 'v1-9450', 'config.ante' => true])
        ->fillForm(['config.ante' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($game->fresh()->config)->toMatchArray(['variant' => 'v1-9450', 'ante' => false, 'buy' => true, 'bet_steps' => ['1.00', '2.00']])
        ->and((float) $game->fresh()->rtp_target)->toBe(0.945);
});
