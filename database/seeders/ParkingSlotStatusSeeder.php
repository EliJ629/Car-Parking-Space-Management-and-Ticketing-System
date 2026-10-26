<?php

namespace Database\Seeders;

use App\Models\ParkingSlot;
use Illuminate\Database\Seeder;

class ParkingSlotStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'A-01' => ParkingSlot::STATUS_AVAILABLE,
            'A-02' => ParkingSlot::STATUS_OCCUPIED,
            'A-03' => ParkingSlot::STATUS_RESERVED,
            'A-04' => ParkingSlot::STATUS_PENDING_EXIT,
            'A-05' => ParkingSlot::STATUS_OUT_OF_SERVICE,
        ];

        foreach ($statuses as $code => $status) {
            $slot = ParkingSlot::query()->where('code', $code)->first();

            if (! $slot) {
                $this->command?->warn("Skipped {$code}; run ParkingSlotSeeder first.");

                continue;
            }

            $slot->update([
                'status' => $status,
                'out_of_service_reason' => $status === ParkingSlot::STATUS_OUT_OF_SERVICE
                    ? 'Demo maintenance'
                    : null,
                'notes' => $status === ParkingSlot::STATUS_OUT_OF_SERVICE
                    ? 'Seeded demo status'
                    : null,
            ]);
        }

        $this->command?->info('Seeded representative parking-slot statuses: available, occupied, reserved, pending exit, and out of service.');
    }
}
