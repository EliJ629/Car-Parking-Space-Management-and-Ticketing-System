<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vehicle that may not park. One row per plate (compact key). A lifted ban stays on the list as history
 * and is switched on again by the next ban of the same plate.
 */
#[Fillable([
    'plate_number', 'reason', 'incident_count', 'user_id', 'banned_at', 'banned_by', 'lifted_at', 'lifted_by', 'lift_note',
])]
class BannedPlate extends Model
{
    protected function casts(): array
    {
        return ['banned_at' => 'datetime', 'lifted_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->lifted_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }
}
