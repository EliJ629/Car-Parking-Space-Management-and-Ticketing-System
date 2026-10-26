<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'first_hour_fee',
    'additional_hour_fee',
    'daily_maximum',
    'lost_ticket_penalty',
    'overstay_penalty',
    'effective_at',
    'ends_at',
    'reason',
    'created_by',
])]
class ParkingRateSet extends Model
{
    protected function casts(): array
    {
        return [
            'first_hour_fee' => 'decimal:2',
            'additional_hour_fee' => 'decimal:2',
            'daily_maximum' => 'decimal:2',
            'lost_ticket_penalty' => 'decimal:2',
            'overstay_penalty' => 'decimal:2',
            'effective_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function current(): ?self
    {
        return static::effectiveAt(now());
    }

    public static function effectiveAt(?Carbon $time = null): ?self
    {
        $time ??= now();

        return static::query()
            ->where('effective_at', '<=', $time)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $time))
            ->orderByDesc('effective_at')
            ->first();
    }

    /** True when this rate stops applying at a chosen time. */
    public function isTemporary(): bool
    {
        return $this->ends_at !== null;
    }

    /** True when the temporary period has already ended. */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    /**
     * A scheduled rate or a temporary rate may be canceled. The current normal rate is the
     * default the lot falls back to, so it is never deleted by the cancellation action.
     */
    public function canBeCanceled(): bool
    {
        return $this->ends_at !== null || $this->effective_at->isFuture();
    }
}
