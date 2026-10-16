<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'percent', 'proof_label', 'requires_manager_pin', 'active', 'created_by'])]
class ParkingDiscount extends Model
{
    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'requires_manager_pin' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
