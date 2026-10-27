<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('incidents')
            ->where('category', 'Long-parked vehicle')
            ->where('source', 'auto')
            ->whereIn('status', ['open', 'under_review'])
            ->orderBy('id')
            ->get(['id', 'code', 'plate_number', 'location', 'reported_at'])
            ->each(function (object $incident): void {
                $exists = DB::table('bayline_notices')
                    ->whereNull('user_id')
                    ->where('audience_role', 'guard')
                    ->where('subject_type', 'incident')
                    ->where('subject_id', $incident->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('bayline_notices')->insert([
                    'user_id' => null,
                    'audience_role' => 'guard',
                    'code' => 'S-01',
                    'kind' => 'long_parking',
                    'title' => 'Long-parked vehicle',
                    'body' => trim(($incident->plate_number ?: 'Vehicle').' in '.($incident->location ?: '-')." passed the long-parking limit. {$incident->code} is open."),
                    'subject_type' => 'incident',
                    'subject_id' => $incident->id,
                    'data' => null,
                    'group_key' => 's01-'.$incident->id.'-guard',
                    'read_at' => null,
                    'created_at' => $incident->reported_at,
                    'updated_at' => $incident->reported_at,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('bayline_notices')
            ->where('audience_role', 'guard')
            ->where('kind', 'long_parking')
            ->where('code', 'S-01')
            ->delete();
    }
};
