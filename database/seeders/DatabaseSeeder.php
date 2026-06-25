<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@legallink.com'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ]
        );
    }
}
