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
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function effectiveAt(?Carbon $time = null): ?self
    {
        return static::query()
            ->where('effective_at', '<=', $time ?? now())
            ->orderByDesc('effective_at')
            ->first();
    }
}
