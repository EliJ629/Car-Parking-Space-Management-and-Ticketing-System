<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_reservations', function (Blueprint $table): void {
            // Every new reservation waits for the booth attendant. Rows made before this change stay approved.
            $table->string('approval_status', 16)->default('approved')->index();
            $table->boolean('requires_id')->default(false);
            $table->string('id_photo_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parking_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'requires_id', 'id_photo_path', 'approved_at', 'rejected_at', 'rejection_reason']);
        });
    }
};
