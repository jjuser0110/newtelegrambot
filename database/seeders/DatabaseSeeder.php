<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'password' => 'superadmin99999', // hashed by the model cast
                'role' => User::ROLE_SUPERADMIN,
                'bot_limit' => 0,
                'is_active' => true,
            ]
        );
    }
}
