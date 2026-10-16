<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the Driver app and the Security Guard console need.
 * Everything is added, nothing is changed or dropped, so it is safe on the shared database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('make_model', 60)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->softDeletes();
        });

        Schema::table('parking_reservations', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
        });

        Schema::table('parking_sessions', function (Blueprint $table): void {
            $table->foreignId('exit_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('lost_ticket')->default(false);
            $table->string('driver_phone', 20)->nullable();
            $table->timestamp('exit_window_expired_at')->nullable();
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->unsignedInteger('detection_count')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('driver_notified')->default(false);
            $table->string('driver_notice_reason', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropColumn(['detection_count', 'last_seen_at', 'driver_notified', 'driver_notice_reason']);
        });

        Schema::table('parking_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exit_approved_by');
            $table->dropColumn(['lost_ticket', 'driver_phone', 'exit_window_expired_at']);
        });

        Schema::table('parking_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['checked_in_at', 'expired_at', 'canceled_at']);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['make_model', 'contact_number']);
        });
    }
};
