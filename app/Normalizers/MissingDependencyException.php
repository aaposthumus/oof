<?php

namespace App\Normalizers;

use RuntimeException;

/**
 * Thrown when a payload refers to core rows that do not exist yet. The raw
 * response stays pending so a later run can normalize it.
 */
class MissingDependencyException extends RuntimeException {}
