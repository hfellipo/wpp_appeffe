<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::factory()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        // Create Regular Active User
        User::factory()->create([
            'name' => 'Usuário Ativo',
            'email' => 'user@example.com',
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        // Create Inactive User (for testing)
        User::factory()->create([
            'name' => 'Usuário Inativo',
            'email' => 'inactive@example.com',
            'role' => UserRole::User,
            'status' => UserStatus::Inactive,
        ]);
    }
}
