<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $driver = User::firstOrCreate(
            ['email' => 'driver@test.com'],
            [
                'name' => 'Driver Test',
                'phone' => '09171234567',
                'password' => 'Driver123_',
                'role' => User::ROLE_DRIVER,
                'status' => User::STATUS_ACTIVE,
            ],
        );
        $driver->forceFill(['email_verified_at' => now()])->save();

        $driver->vehicles()->firstOrCreate(
            ['plate_number' => 'DEF2208'],
            ['vehicle_type' => 'Car', 'color' => 'White'],
        );
    }
}
