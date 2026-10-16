<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code',
    'category',
    'source',
    'session_id',
    'slot_id',
    'plate_number',
    'location',
    'description',
    'status',
    'resolution_note',
    'reported_by',
    'resolved_by',
    'reported_at',
    'resolved_at',
])]
class Incident extends Model
{
    public const CATEGORIES = [
        'Long-parked vehicle',
        'Unauthorized parking',
        'Unpaid vehicle attempting exit',
        'Vehicle damage',
        'Suspicious vehicle',
        'Violation',
        'Other',
    ];

    public const STATUSES = ['open', 'under_review', 'resolved'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ParkingSession::class, 'session_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }
}
