<?php

namespace Database\Seeders;

use App\Domain\Game\Models\Game;
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
                'name' => 'Orange Crash',
                'version' => 1,
                'rtp_target' => 0.97,
                'house_edge' => 0.03,
                'config' => ['growth_rate' => 0.06, 'max_bet' => 1000, 'max_win' => 100000],
                'status' => 'active',
            ],
        );

        if (app()->environment(['local', 'testing'])) {
            $this->call(ConsumerOperatorSeeder::class);
        }
    }
}
