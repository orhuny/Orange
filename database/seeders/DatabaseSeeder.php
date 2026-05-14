<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@orange.local'],
            [
                'name' => 'Orange Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
