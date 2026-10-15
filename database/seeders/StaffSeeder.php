<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Test staff accounts. Staff sign in with the email below on /staff/login.
 *
 * Safe to run more than once: an account that already exists is left alone,
 * so a changed password is never put back.
 *
 * These are for testing only. Remove or change them before a real demo.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Staff Test', 'email' => 'staff@test.com', 'role' => User::ROLE_ATTENDANT, 'password' => 'Staff123_'],
            ['name' => 'Manager Test', 'email' => 'manager@test.com', 'role' => User::ROLE_MANAGER, 'password' => 'Manager123_'],
            ['name' => 'Guard Test', 'email' => 'guard@test.com', 'role' => User::ROLE_GUARD, 'password' => 'Guard123_'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                $account + ['status' => User::STATUS_ACTIVE],
            );
        }
    }
}
