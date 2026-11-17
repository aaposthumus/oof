<?php

namespace App\Achievements;

use RuntimeException;

/**
 * An award that may not exist: a withheld achievement (crashes, the Safety
 * Car, Built Different) on a sensitive weekend, or anything in a blocked
 * round or for a blocked driver.
 */
class WithheldOnSensitiveWeekend extends RuntimeException {}
