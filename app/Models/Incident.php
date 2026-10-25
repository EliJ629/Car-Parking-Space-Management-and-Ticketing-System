<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    'detection_count',
    'last_seen_at',
    'driver_notified',
    'driver_notice_reason',
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
        'Mismatched plate or details',
        'Other',
    ];

    /**
     * What a report of each category must include. plate: required or optional. place: slot (an existing slot code),
     * location (any place in the lot, typed), auto (taken from the parked vehicle), or optional.
     * A plate that is given must belong to a vehicle that is parked now.
     */
    public const REQUIREMENTS = [
        'Unauthorized parking' => ['plate' => 'required', 'place' => 'slot'],
        'Unpaid vehicle attempting exit' => ['plate' => 'required', 'place' => 'auto'],
        'Vehicle damage' => ['plate' => 'required', 'place' => 'slot'],
        'Suspicious vehicle' => ['plate' => 'optional', 'place' => 'location'],
        'Violation' => ['plate' => 'required', 'place' => 'slot'],
        'Mismatched plate or details' => ['plate' => 'required', 'place' => 'slot'],
        'Other' => ['plate' => 'optional', 'place' => 'optional'],
    ];

    /** @return array{plate:string,place:string} */
    public static function requirements(?string $category): array
    {
        return self::REQUIREMENTS[(string) $category] ?? ['plate' => 'optional', 'place' => 'optional'];
    }

    public const STATUSES = ['open', 'under_review', 'resolved'];

    /** What the Security Guard screens call a category, where the wording differs from the stored value. */
    public const GUARD_LABELS = [
        'Suspicious vehicle' => 'Suspicious vehicle or individual',
        'Violation' => 'Parking violation',
    ];

    public static function guardLabel(string $category): string
    {
        return self::GUARD_LABELS[$category] ?? $category;
    }

    public static function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'driver_notified' => 'boolean',
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

    public function photos(): HasMany
    {
        return $this->hasMany(IncidentPhoto::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class)->orderBy('created_at')->orderBy('id');
    }
}
