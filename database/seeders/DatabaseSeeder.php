<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'role' => UserRole::Customer,
        ]);

        User::factory()->create([
            'name' => 'Test Organizer',
            'email' => 'organizer@example.com',
            'role' => UserRole::Organizer,
        ]);

        User::factory()->create([
            'name' => 'Test Gatekeeper',
            'email' => 'gatekeeper@example.com',
            'role' => UserRole::Gatekeeper,
        ]);

        $this->call([
            AdminSeeder::class,
            EventSeeder::class,
            FaqSeeder::class,
        ]);
    }
}
