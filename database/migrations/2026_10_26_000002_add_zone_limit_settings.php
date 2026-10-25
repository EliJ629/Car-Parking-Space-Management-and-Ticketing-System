<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'maximum_slots_per_zone',
                'value' => json_encode(20),
                'group' => 'parking',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'maximum_active_zones',
                'value' => json_encode(26),
                'group' => 'parking',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('key', ['maximum_slots_per_zone', 'maximum_active_zones'])
            ->delete();
    }
};
