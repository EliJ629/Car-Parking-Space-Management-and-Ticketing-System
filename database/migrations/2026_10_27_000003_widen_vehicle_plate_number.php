<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plate list now includes temporary transit plates for motorcycles (1234-12345678901), which are 16
 * characters in the stored key. vehicles.plate_number held 12. Every other table that stores a plate already
 * holds 20 or more. Widening a varchar never changes or loses data.
 *
 * Rollback: the column goes back to 12 only when no stored plate is longer than that, so a rollback can never cut a plate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('plate_number', 20)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('vehicles')->whereRaw('LENGTH(plate_number) > 12')->exists()) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('plate_number', 12)->change();
        });
    }
};
