<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A plate a vehicle carried before its current one (a temporary plate that was replaced by the permanent plate). */
#[Fillable(['vehicle_id', 'user_id', 'plate_number', 'was_temporary', 'replaced_at'])]
class VehiclePreviousPlate extends Model
{
    protected function casts(): array
    {
        return ['was_temporary' => 'boolean', 'replaced_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }
}
