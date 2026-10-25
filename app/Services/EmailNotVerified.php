<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

/** A driver gave the right password, but the email has not been confirmed with the code yet. */
class EmailNotVerified extends RuntimeException
{
    public function __construct(public User $user)
    {
        parent::__construct('Confirm your email to continue.');
    }
}
