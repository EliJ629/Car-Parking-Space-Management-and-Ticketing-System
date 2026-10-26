<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['zone', 'vehicle_type', 'label_x', 'label_y'])]
class ParkingLayoutZone extends Model
{
    public const VEHICLE_CAR = 'Car';

    public const VEHICLE_MOTORCYCLE = 'Motorcycle';

    public static function vehicleTypes(): array
    {
        return [self::VEHICLE_CAR, self::VEHICLE_MOTORCYCLE];
    }

    protected function casts(): array
    {
        return [
            'label_x' => 'float',
            'label_y' => 'float',
        ];
    }
}
