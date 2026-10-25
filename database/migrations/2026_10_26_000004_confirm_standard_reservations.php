<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Only PWD reservations are approved by the booth attendant now. A reservation for any other
     * slot that was still waiting is confirmed, so its QR code works and nobody has to approve it.
     */
    public function up(): void
    {
        DB::table('parking_reservations')
            ->where('approval_status', 'pending')
            ->where('requires_id', false)
            ->update(['approval_status' => 'approved', 'approved_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to undo: the rows were only waiting.
    }
};
