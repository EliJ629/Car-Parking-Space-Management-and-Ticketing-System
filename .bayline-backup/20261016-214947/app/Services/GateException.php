<?php

namespace App\Services;

use RuntimeException;

/** Good to Go was refused. The reason tells the screen which message to show. */
class GateException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
