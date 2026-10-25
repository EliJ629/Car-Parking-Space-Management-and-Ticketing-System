<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_reservations', function (Blueprint $table): void {
            // When the SMS reminder was handed to TextBee. Empty means it was not sent yet.
            $table->timestamp('reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parking_reservations', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
