<?php

namespace App\Services;

use RuntimeException;

/** The mail service refused or could not send the code, for example because the address does not exist. */
class VerificationMailFailed extends RuntimeException {}
