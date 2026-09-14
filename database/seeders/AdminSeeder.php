<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the system admin account from the ADMIN_EMAIL whitelist.
     */
    public function run(): void
    {
        foreach (config('access.admin_emails', []) as $email) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'System Admin',
                    'password' => config('access.admin_password'),
                    'role' => UserRole::Admin,
                    'is_active' => true,
                ],
            );
        }
    }
}
