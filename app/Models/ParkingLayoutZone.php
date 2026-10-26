<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['zone', 'label_x', 'label_y'])]
class ParkingLayoutZone extends Model
{
    protected function casts(): array
    {
        return [
            'label_x' => 'float',
            'label_y' => 'float',
        ];
    }
}
