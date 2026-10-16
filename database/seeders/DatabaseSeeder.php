<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with test accounts.
     *
     * Safe to run more than once, which matters because the whole team shares one database.
     *
     *   staff@test.com    Staff123_     (staff sign in)
     *   manager@test.com  Manager123_   (staff sign in)
     *   guard@test.com    Guard123_     (staff sign in)
     *   driver@test.com   Driver123_    (driver sign in)
     */
    public function run(): void
    {
        $this->call([
            StaffSeeder::class,
            ParkingSlotSeeder::class,
            ManagerDefaultsSeeder::class,
        ]);

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

        $driver->vehicles()->firstOrCreate(
            ['plate_number' => 'DEF2208'],
            ['vehicle_type' => 'Car', 'color' => 'White'],
        );
    }
}
