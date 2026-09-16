<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a basket asks for more units than the shelf holds. Callers turn
 * this into a validation error rather than a 500 — it is an expected outcome
 * of two people wanting the last shirt.
 */
class InsufficientStockException extends RuntimeException {}
