<?php

namespace App\Services;

use RuntimeException;

/** TextBee did not take the message. The text of the exception says what to check. */
class TextBeeFailed extends RuntimeException {}
