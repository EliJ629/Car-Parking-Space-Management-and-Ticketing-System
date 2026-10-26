<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permission to drive out, issued when a payment is recorded. It is active until the Guard uses it or its time
 * ends. An expired authorization is kept; the next payment issues a new one with a new code (EXA-000123).
 */
#[Fillable([
    'code', 'session_id', 'payment_id', 'sequence', 'status', 'amount', 'cumulative_paid', 'receipt_number', 'issued_by',
    'issued_at', 'expires_at', 'expired_at', 'used_at', 'used_by', 'notified_at', 'notify_channel', 'notify_result',
])]
class ExitAuthorization extends Model
{
    public const ACTIVE = 'active';

    public const USED = 'used';

    public const EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cumulative_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'used_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'session_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ParkingPayment::class, 'payment_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Everything this session has paid so far, across every payment that issued a permission. The payment row holds
     * only the latest payment, so the ledger is what keeps an earlier payment from being charged again.
     */
    public static function paidFor(ParkingSession $session): float
    {
        $rows = static::query()->where('session_id', $session->id);

        if ($rows->exists()) {
            return round((float) $rows->sum('amount'), 2);
        }

        return round((float) ($session->payment?->total ?? 0.0), 2);
    }

    /** EXA-000001, in order. A unique index on the code settles two requests at the same moment. */
    public static function nextCode(): string
    {
        $highest = 0;

        foreach (static::query()->where('code', 'like', 'EXA-%')->pluck('code') as $code) {
            if (preg_match('/^EXA-(\d+)$/', $code, $m)) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        return 'EXA-'.str_pad((string) ($highest + 1), 6, '0', STR_PAD_LEFT);
    }
}
