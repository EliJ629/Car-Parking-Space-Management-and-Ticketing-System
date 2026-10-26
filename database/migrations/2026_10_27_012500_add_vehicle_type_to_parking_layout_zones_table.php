<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_layout_zones', function (Blueprint $table): void {
            $table->string('vehicle_type', 20)->default('Car')->after('zone');
        });
    }

    public function down(): void
    {
        Schema::table('parking_layout_zones', function (Blueprint $table): void {
            $table->dropColumn('vehicle_type');
        });
    }
};
