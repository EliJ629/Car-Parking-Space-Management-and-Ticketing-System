<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three small additions. Nothing in an existing row is changed or deleted.
 *
 *   vehicles.plate_is_temporary, pending_registrations.plate_is_temporary
 *       A plate entered while the vehicle is "still in registration" is a temporary plate. It can later be replaced once,
 *       by the permanent plate. Every existing vehicle starts as permanent (false).
 *
 *   vehicle_previous_plates
 *       The plates a vehicle carried before it got its permanent plate. Violations on any of them count for the same
 *       vehicle, so a ban cannot be avoided by swapping the temporary plate for the real one.
 *
 *   reservation_suspensions
 *       One row each time a driver is blocked from reserving because of missed reservations (no-shows): who, how many no-shows,
 *       from when to when. The block never touches walking in and parking.
 *
 * Rollback: php artisan migrate:rollback --step=1 drops the two new tables and the two new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->boolean('plate_is_temporary')->default(false)->after('plate_number');
        });

        Schema::table('pending_registrations', function (Blueprint $table): void {
            $table->boolean('plate_is_temporary')->default(false)->after('plate_number');
        });

        Schema::create('vehicle_previous_plates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate_number', 20)->index();
            $table->boolean('was_temporary')->default(true);
            $table->timestamp('replaced_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('reservation_suspensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('no_show_count');
            $table->unsignedInteger('tier');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->string('reason');
            $table->timestamps();
            $table->index(['user_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_suspensions');
        Schema::dropIfExists('vehicle_previous_plates');

        Schema::table('pending_registrations', function (Blueprint $table): void {
            $table->dropColumn('plate_is_temporary');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn('plate_is_temporary');
        });
    }
};
