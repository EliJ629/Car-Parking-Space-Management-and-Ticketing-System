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
    'damaged_plate',
    'responsible_plate',
    'attributed_plate',
    'other_label',
    'event_key',
    'confirmation',
    'confirmed_at',
    'confirmed_by',
    'confirmation_note',
    'driver_notified_at',
    'driver_notice_channel',
    'driver_notice_result',
])]
class Incident extends Model
{
    public const CATEGORIES = [
        'Long-parked vehicle',
        'Unauthorized parking',
        'Wrong slot',
        'Unpaid vehicle attempting exit',
        'Vehicle damage',
        'Suspicious vehicle',
        'Violation',
        'Mismatched plate or details',
        'Other',
    ];

    /** Long-parking incidents are made by the system only (LongParkingService). A Guard cannot report them by hand. */
    public const MANUAL_CATEGORIES = [
        'Unauthorized parking',
        'Wrong slot',
        'Unpaid vehicle attempting exit',
        'Vehicle damage',
        'Suspicious vehicle',
        'Violation',
        'Mismatched plate or details',
        'Other',
    ];

    /** Confirmation: a Manager (or the system, for automatic long-parking) says the report is valid. */
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const DISMISSED = 'dismissed';

    /** The categories that count toward the three-incident ban once confirmed. */
    public const BAN_CATEGORIES = ['Vehicle damage', 'Long-parked vehicle', 'Unauthorized parking', 'Wrong slot', 'Violation'];

    /**
     * What a report of each category must include. plate: required or optional. place: slot (an occupied slot),
     * location (an occupied slot or any place in the lot, typed), auto (taken from the parked vehicle), or optional.
     * A plate that is given must belong to a vehicle that is parked now. responsible: the plate of the vehicle at
     * fault (damage reports). label: a typed name for the kind of incident ("Other").
     */
    public const REQUIREMENTS = [
        'Unauthorized parking' => ['plate' => 'required', 'place' => 'slot'],
        'Wrong slot' => ['plate' => 'required', 'place' => 'slot'],
        'Unpaid vehicle attempting exit' => ['plate' => 'required', 'place' => 'auto'],
        'Vehicle damage' => ['plate' => 'required', 'place' => 'slot', 'responsible' => 'required'],
        'Suspicious vehicle' => ['plate' => 'optional', 'place' => 'location'],
        'Violation' => ['plate' => 'required', 'place' => 'slot'],
        'Mismatched plate or details' => ['plate' => 'required', 'place' => 'slot'],
        'Other' => ['plate' => 'optional', 'place' => 'optional', 'label' => 'required'],
    ];

    /** @return array{plate:string,place:string,responsible?:string,label?:string} */
    public static function requirements(?string $category): array
    {
        return self::REQUIREMENTS[(string) $category] ?? ['plate' => 'optional', 'place' => 'optional'];
    }

    /** The plate this incident counts against: the responsible vehicle for damage, the vehicle itself otherwise. */
    public static function attributedPlateFor(string $category, ?string $plate, ?string $responsible): ?string
    {
        if (! in_array($category, self::BAN_CATEGORIES, true)) {
            return null;
        }

        $value = $category === 'Vehicle damage' ? $responsible : $plate;

        return $value !== null && $value !== '' ? $value : null;
    }

    /** What the screens call this incident: the typed name for "Other". */
    public function title(): string
    {
        return $this->category === 'Other' && $this->other_label ? 'Other: '.$this->other_label : self::guardLabel($this->category);
    }

    public const STATUSES = ['open', 'under_review', 'resolved'];

    /** What the Security Guard screens call a category, where the wording differs from the stored value. */
    public const GUARD_LABELS = [
        'Wrong slot' => 'Parked in the wrong slot',
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
            'confirmed_at' => 'datetime',
            'driver_notified_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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
