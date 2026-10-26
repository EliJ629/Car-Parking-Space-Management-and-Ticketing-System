<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each zone is a car zone (cars, SUVs, vans, pickups, tricycles) or a motorcycle zone. Every slot already in the lot
     * stays a car slot. The Parking Manager marks a zone as a motorcycle zone on the slot form.
     */
    public function up(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->string('vehicle_class', 12)->default('car')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->dropColumn('vehicle_class');
        });
    }
};
