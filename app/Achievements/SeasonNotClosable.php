<?php

namespace App\Achievements;

use RuntimeException;

/**
 * A season that cannot be closed yet or is closed already, or cannot be reopened because it is open.
 */
class SeasonNotClosable extends RuntimeException {}
