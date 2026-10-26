<?php

namespace App\Services;

use RuntimeException;

/** Google did not give us a usable answer. The message is safe to show on the sign in page. */
class GoogleSignInFailed extends RuntimeException {}
