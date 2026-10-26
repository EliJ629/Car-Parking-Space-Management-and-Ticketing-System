<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One migration for three connected features. Nothing is deleted or rewritten:
 *
 *  1. incidents: who is responsible (attributed plate), whether a Manager confirmed it, a key that stops
 *     the same event from being counted twice, and how the driver was told.
 *  2. banned_plates: the list of vehicles blocked after the third confirmed incident.
 *  3. exit_authorizations: one row for every exit permission the booth issues, so an expired permission
 *     and the one that replaces it both stay in the history.
 *
 * Existing rows are filled in conservatively: an old report is never attributed to a plate that the
 * report did not clearly name as responsible, and nothing is counted toward a ban until it is confirmed.
 * Rolling back drops the new tables and columns only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('damaged_plate', 20)->nullable();
            $table->string('responsible_plate', 20)->nullable();
            $table->string('attributed_plate', 20)->nullable();
            $table->string('other_label', 80)->nullable();
            $table->string('event_key', 120)->nullable()->unique();
            $table->string('confirmation', 12)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confirmation_note', 255)->nullable();
            $table->timestamp('driver_notified_at')->nullable();
            $table->string('driver_notice_channel', 8)->nullable();
            $table->string('driver_notice_result', 160)->nullable();

            $table->index(['attributed_plate', 'confirmation']);
        });

        Schema::create('banned_plates', function (Blueprint $table): void {
            $table->id();
            $table->string('plate_number', 20)->unique();
            $table->string('reason', 255);
            $table->unsignedSmallInteger('incident_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('banned_at');
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lift_note', 255)->nullable();
            $table->timestamps();

            $table->index('lifted_at');
        });

        Schema::create('exit_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('session_id')->constrained('parking_sessions')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('parking_payments')->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('status', 12)->default('active')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('cumulative_paid', 10, 2)->default(0);
            $table->string('receipt_number', 30)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->string('notify_channel', 8)->nullable();
            $table->string('notify_result', 160)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'sequence']);
        });

        $this->backfillIncidents();
        $this->backfillAuthorizations();
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_authorizations');
        Schema::dropIfExists('banned_plates');

        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex(['attributed_plate', 'confirmation']);
            $table->dropUnique(['event_key']);
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn([
                'damaged_plate', 'responsible_plate', 'attributed_plate', 'other_label', 'event_key', 'confirmation',
                'confirmed_at', 'confirmation_note', 'driver_notified_at', 'driver_notice_channel', 'driver_notice_result',
            ]);
        });
    }

    private function backfillIncidents(): void
    {
        // Long-parking and the two vehicle-fault categories already name the vehicle in plate_number.
        // Damage reports from before this change recorded one plate only, and it is not known which vehicle it was,
        // so they are left unattributed instead of guessed.
        DB::table('incidents')
            ->whereIn('category', ['Long-parked vehicle', 'Unauthorized parking', 'Violation'])
            ->whereNotNull('plate_number')
            ->update(['attributed_plate' => DB::raw('plate_number')]);

        // Automatic long-parking alerts come from the system's own clock and need no review.
        DB::table('incidents')
            ->where('category', 'Long-parked vehicle')
            ->where('source', 'auto')
            ->update(['confirmation' => 'confirmed', 'confirmed_at' => DB::raw('reported_at')]);

        // One long-parking incident per parking session carries the event key (the oldest one).
        $oldest = DB::table('incidents')
            ->where('category', 'Long-parked vehicle')
            ->whereNotNull('session_id')
            ->selectRaw('MIN(id) AS id, session_id')
            ->groupBy('session_id')
            ->get();

        foreach ($oldest as $row) {
            DB::table('incidents')->where('id', $row->id)->update(['event_key' => 'long:'.$row->session_id]);
        }
    }

    private function backfillAuthorizations(): void
    {
        $window = (int) (json_decode((string) DB::table('system_settings')->where('key', 'exit_window_minutes')->value('value'), true) ?: 15);
        $number = 0;

        DB::table('parking_payments')
            ->join('parking_sessions', 'parking_sessions.id', '=', 'parking_payments.session_id')
            ->select([
                'parking_payments.id as payment_id', 'parking_payments.session_id', 'parking_payments.total', 'parking_payments.receipt_number',
                'parking_payments.received_by', 'parking_payments.paid_at', 'parking_sessions.status', 'parking_sessions.exited_at',
                'parking_sessions.exit_approved_by', 'parking_sessions.exit_window_expired_at',
            ])
            ->orderBy('parking_payments.id')
            ->get()
            ->each(function (object $row) use ($window, &$number): void {
                $paidAt = \Illuminate\Support\Carbon::parse($row->paid_at);
                $expiresAt = $paidAt->copy()->addMinutes($window);
                $number++;

                $status = match ($row->status) {
                    'completed' => 'used',
                    'paid' => 'active',
                    default => 'expired',
                };

                DB::table('exit_authorizations')->insert([
                    'code' => 'EXA-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'session_id' => $row->session_id,
                    'payment_id' => $row->payment_id,
                    'sequence' => 1,
                    'status' => $status,
                    'amount' => $row->total,
                    'cumulative_paid' => $row->total,
                    'receipt_number' => $row->receipt_number,
                    'issued_by' => $row->received_by,
                    'issued_at' => $paidAt,
                    'expires_at' => $expiresAt,
                    'expired_at' => $status === 'expired' ? ($row->exit_window_expired_at ?? $expiresAt) : null,
                    'used_at' => $status === 'used' ? ($row->exited_at ?? $paidAt) : null,
                    'used_by' => $status === 'used' ? $row->exit_approved_by : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }
};
