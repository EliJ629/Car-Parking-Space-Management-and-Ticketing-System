<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver notifications (N-01 to N-13) and role alerts for staff (S-01 to S-06).
 * A driver notice belongs to one user. A role alert belongs to a role and is
 * marked read for everyone in that role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bayline_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('audience_role', 20)->nullable()->index();
            $table->string('code', 8)->index();
            $table->string('kind', 40);
            $table->string('title', 120);
            $table->text('body');
            $table->string('subject_type', 20)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->string('group_key', 80)->nullable()->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['audience_role', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bayline_notices');
    }
};
