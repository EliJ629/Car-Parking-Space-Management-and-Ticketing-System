<?php

namespace App\Services;

use RuntimeException;

/** A parking action was refused because the vehicle is on the banned list. The message is safe to show to anyone. */
class VehicleBanned extends RuntimeException
{
    public const MESSAGE = 'This vehicle is banned from parking because of numerous incident reports. Please contact the Parking Manager.';

    public function __construct(string $message = self::MESSAGE)
    {
        parent::__construct($message);
    }
}
