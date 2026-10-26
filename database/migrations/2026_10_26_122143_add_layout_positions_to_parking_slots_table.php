<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->decimal('layout_x', 8, 6)->nullable()->after('out_of_service_reason');
            $table->decimal('layout_y', 8, 6)->nullable()->after('layout_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->dropColumn(['layout_x', 'layout_y']);
        });
    }
};
