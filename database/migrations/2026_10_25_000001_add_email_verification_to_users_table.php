<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // The same mailbox and the same name written one way, so duplicates can be found (Gmail ignores dots).
            $table->string('email_key')->nullable()->index();
            $table->string('name_key')->nullable()->index();

            // The six digit code sent to the email, kept as a hash. Also used when a driver changes email or phone.
            $table->string('verification_code_hash')->nullable();
            $table->timestamp('verification_code_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamp('verification_sent_at')->nullable();

            // A new email or phone waits here until the driver confirms it with the code.
            $table->string('pending_email')->nullable();
            $table->string('pending_phone', 20)->nullable();
        });

        // Accounts that exist today were created before the code step, so they count as verified.
        DB::table('users')->orderBy('id')->each(function ($user): void {
            $email = mb_strtolower(trim((string) $user->email));
            $key = $email;

            if (str_contains($email, '@')) {
                [$local, $domain] = explode('@', $email, 2);

                if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
                    $key = str_replace('.', '', explode('+', $local, 2)[0]).'@gmail.com';
                }
            }

            DB::table('users')->where('id', $user->id)->update([
                'email_key' => $email === '' ? null : $key,
                'name_key' => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower((string) $user->name)),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'email_key', 'name_key', 'verification_code_hash', 'verification_code_expires_at',
                'verification_attempts', 'verification_sent_at', 'pending_email', 'pending_phone',
            ]);
        });
    }
};
