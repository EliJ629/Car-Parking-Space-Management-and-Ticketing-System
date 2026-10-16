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
        Schema::create('parking_rate_sets', function (Blueprint $table): void {
            $table->id();
            $table->decimal('first_hour_fee', 10, 2);
            $table->decimal('additional_hour_fee', 10, 2);
            $table->decimal('daily_maximum', 10, 2);
            $table->decimal('lost_ticket_penalty', 10, 2);
            $table->decimal('overstay_penalty', 10, 2);
            $table->timestamp('effective_at')->index();
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('parking_discounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedTinyInteger('percent');
            $table->string('proof_label', 80);
            $table->boolean('requires_manager_pin')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->string('group', 40)->index();
            $table->timestamps();
        });

        Schema::create('parking_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            $table->string('slot_code', 10)->nullable();
            $table->foreignId('attendant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('plate_number', 20)->index();
            $table->string('vehicle_color', 30)->nullable();
            $table->string('vehicle_type', 30)->nullable();
            $table->timestamp('entered_at')->index();
            $table->timestamp('exited_at')->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('parking_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('session_id')->unique()->constrained('parking_sessions')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('base_fee', 10, 2);
            $table->decimal('additional_fee', 10, 2)->default(0);
            $table->decimal('penalty', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('method', 20)->default('cash');
            $table->json('receipt_snapshot')->nullable();
            $table->timestamp('paid_at')->index();
            $table->timestamps();
        });

        Schema::create('parking_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            $table->string('slot_code', 10)->nullable();
            $table->string('plate_number', 20)->index();
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 20)->default('booked')->index();
            $table->foreignId('session_id')->nullable()->constrained('parking_sessions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('category', 60)->index();
            $table->string('source', 20)->default('manual')->index();
            $table->foreignId('session_id')->nullable()->constrained('parking_sessions')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            $table->string('plate_number', 20)->nullable()->index();
            $table->string('location', 120)->nullable();
            $table->text('description');
            $table->string('status', 24)->default('open')->index();
            $table->text('resolution_note')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('parking_reservations');
        Schema::dropIfExists('parking_payments');
        Schema::dropIfExists('parking_sessions');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('parking_discounts');
        Schema::dropIfExists('parking_rate_sets');
    }
};
