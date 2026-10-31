<?php

namespace Database\Seeders;

use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameCategory;
use App\Domain\Game\Models\GameLimit;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        AccessControl::sync();

        User::updateOrCreate(
            ['email' => 'admin@orange.local'],
            [
                'name' => 'Orange Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        )->assignRole(AccessControl::SUPER_ADMIN);

        Game::updateOrCreate(
            ['code' => 'crash'],
            [
                'type' => 'crash',
                'slug' => 'crash',
                'access' => 'all',
                'category_id' => GameCategory::query()->where('slug', 'crash')->value('id'),
                'name' => 'Orange Crash',
                'version' => 1,
                'rtp_target' => 0.97,
                'house_edge' => 0.03,
                'config' => ['growth_rate' => 0.06, 'max_bet' => 1000, 'max_win' => 100000],
                'status' => 'active',
            ],
        );

        // Dice: open only to operators it is enabled for. The EUR limits are placeholders
        // until the real per-currency limits are decided (docs/multi-game-plan.md §13).
        $dice = Game::updateOrCreate(
            ['code' => 'dice'],
            [
                'type' => 'dice',
                'slug' => 'dice',
                'access' => 'listed',
                'category_id' => GameCategory::query()->where('slug', 'originals')->value('id'),
                'name' => 'Dice',
                'version' => 1,
                'rtp_target' => 0.99,
                'house_edge' => 0.01,
                'config' => ['min_chance' => '0.01', 'max_chance' => '98.00'],
                'status' => 'active',
            ],
        );
        GameLimit::updateOrCreate(
            ['game_id' => $dice->id, 'currency' => 'EUR'],
            ['min_bet' => '0.10', 'max_bet' => '100.00', 'max_win' => '10000.00'],
        );

        if (app()->environment(['local', 'testing'])) {
            $this->call(ConsumerOperatorSeeder::class);
        }
    }
}
