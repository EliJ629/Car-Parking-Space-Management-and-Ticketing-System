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
        Schema::create('parking_layout_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone')->unique();
            $table->decimal('label_x', 8, 6)->nullable();
            $table->decimal('label_y', 8, 6)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_layout_zones');
    }
};
