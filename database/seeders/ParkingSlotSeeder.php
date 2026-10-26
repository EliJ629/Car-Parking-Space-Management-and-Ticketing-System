<?php

namespace Database\Seeders;

use App\Models\ParkingSlot;
use Illuminate\Database\Seeder;

class ParkingSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (range('A', 'D') as $zoneLetter) {
            foreach (range(1, 10) as $slotNumber) {
                $code = sprintf('%s-%02d', $zoneLetter, $slotNumber);

                ParkingSlot::firstOrCreate(
                    ['code' => $code],
                    [
                        'zone' => "Zone {$zoneLetter}",
                        'type' => in_array($slotNumber, [1, 2], true)
                            ? ParkingSlot::TYPE_PWD
                            : ParkingSlot::TYPE_STANDARD,
                        // Zone D is the motorcycle zone of a new lot. The Manager can change it on the slot form.
                        'vehicle_class' => $zoneLetter === 'D' ? ParkingSlot::CLASS_MOTORCYCLE : ParkingSlot::CLASS_CAR,
                        'status' => ParkingSlot::STATUS_AVAILABLE,
                    ],
                );
            }
        }
    }
}
