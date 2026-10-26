<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A sign up waits here until the person clicks the link in the email. Only then is it copied into
        // users and vehicles, so an unconfirmed email never becomes an account.
        Schema::create('pending_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('email_key')->unique();
            $table->string('phone', 32);
            $table->string('plate_number', 32);
            $table->string('vehicle_type', 32);
            $table->string('color', 64)->nullable();
            $table->string('make_model', 120)->nullable();
            $table->string('password');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
