<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A vehicle that is still being registered has no plate number yet. The driver signs up with "no plate yet" ticked and
 * sets the plate after signing in, once the LTO has issued it. An empty plate is stored as NULL (not as an empty text),
 * and several vehicles can have NULL at the same time because the unique key on (user_id, plate_number) ignores NULLs.
 *
 * Nothing is deleted or changed in existing rows.
 *
 * Rollback: each column goes back to "plate required" only when no row in it has an empty plate; otherwise it is left nullable, so a rollback can never lose a vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('plate_number', 20)->nullable()->change();
        });

        Schema::table('pending_registrations', function (Blueprint $table): void {
            $table->string('plate_number', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! DB::table('vehicles')->whereNull('plate_number')->exists()) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->string('plate_number', 20)->nullable(false)->change();
            });
        }

        if (! DB::table('pending_registrations')->whereNull('plate_number')->exists()) {
            Schema::table('pending_registrations', function (Blueprint $table): void {
                $table->string('plate_number', 32)->nullable(false)->change();
            });
        }
    }
};
