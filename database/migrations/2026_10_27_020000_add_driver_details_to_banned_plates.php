<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The banned list shows who is banned, not only the plate: the driver's name and mobile number are kept on the ban,
 * so a banned person cannot sign up again with the same name or number. Bans made before this change take the name
 * and number from the linked account or from the vehicle's parking sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banned_plates', function (Blueprint $table): void {
            $table->string('driver_name', 120)->nullable()->after('plate_number');
            $table->string('driver_phone', 20)->nullable()->after('driver_name');
            $table->index('driver_phone');
        });

        DB::table('banned_plates')->orderBy('id')->get(['id', 'plate_number', 'user_id'])->each(function (object $ban): void {
            $user = $ban->user_id ? DB::table('users')->where('id', $ban->user_id)->first(['name', 'phone']) : null;
            $phone = $user->phone ?? DB::table('parking_sessions')->where('plate_number', $ban->plate_number)->whereNotNull('driver_phone')->orderByDesc('id')->value('driver_phone');

            DB::table('banned_plates')->where('id', $ban->id)->update(['driver_name' => $user->name ?? null, 'driver_phone' => $phone]);
        });
    }

    public function down(): void
    {
        Schema::table('banned_plates', function (Blueprint $table): void {
            $table->dropIndex(['driver_phone']);
            $table->dropColumn(['driver_name', 'driver_phone']);
        });
    }
};
