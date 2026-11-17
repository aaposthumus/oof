<?php

namespace App\Achievements;

use RuntimeException;

/**
 * A season that cannot be closed yet, or is closed already.
 */
class SeasonNotClosable extends RuntimeException {}
