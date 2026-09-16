<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when something tries to move an order to a state the workflow does
 * not allow from where it currently stands.
 */
class InvalidStatusTransitionException extends RuntimeException {}
