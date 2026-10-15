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
        Schema::table('users', function (Blueprint $table) {
            // manager, attendant, guard, driver. The sign-in pages never show this.
            $table->string('role', 20)->default('driver')->after('password')->index();
            // Staff sign in with a username or an email. Drivers use their email.
            $table->string('username', 40)->nullable()->unique()->after('name');
            $table->string('phone', 20)->nullable()->after('email');
            // active, suspended or inactive. Only active accounts can sign in.
            $table->string('status', 20)->default('active')->after('role');
            // Five wrong passwords in a row lock the account for 15 minutes.
            $table->unsignedSmallInteger('failed_login_count')->default(0)->after('status');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'role',
                'username',
                'phone',
                'status',
                'failed_login_count',
                'locked_until',
            ]);
        });
    }
};
