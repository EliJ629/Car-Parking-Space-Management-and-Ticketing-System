<?php

namespace App\Services;

use RuntimeException;

/** A reservation could not be made. The code says why, so the screen can show the right pop-up. */
class ReservationException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message, public readonly ?object $context = null)
    {
        parent::__construct($message);
    }
}
