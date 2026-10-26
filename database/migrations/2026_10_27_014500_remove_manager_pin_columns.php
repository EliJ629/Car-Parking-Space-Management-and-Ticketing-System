<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'manager_pin_hash')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('manager_pin_hash');
            });
        }

        if (Schema::hasColumn('parking_discounts', 'requires_manager_pin')) {
            Schema::table('parking_discounts', function (Blueprint $table): void {
                $table->dropColumn('requires_manager_pin');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('manager_pin_hash')->nullable();
        });

        Schema::table('parking_discounts', function (Blueprint $table): void {
            $table->boolean('requires_manager_pin')->default(false)->index();
        });
    }
};
