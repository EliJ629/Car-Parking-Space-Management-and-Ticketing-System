<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 60)->nullable()->after('name');
            $table->string('last_name', 60)->nullable()->after('first_name');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2);
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
