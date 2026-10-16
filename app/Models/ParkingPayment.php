<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'receipt_number',
    'session_id',
    'received_by',
    'base_fee',
    'additional_fee',
    'penalty',
    'discount',
    'total',
    'method',
    'receipt_snapshot',
    'paid_at',
])]
class ParkingPayment extends Model
{
    protected function casts(): array
    {
        return [
            'base_fee' => 'decimal:2',
            'additional_fee' => 'decimal:2',
            'penalty' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'receipt_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'session_id');
    }

    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
