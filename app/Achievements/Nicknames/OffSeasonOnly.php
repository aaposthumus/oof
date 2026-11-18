<?php

namespace App\Achievements\Nicknames;

use RuntimeException;

/**
 * A nickname evolution takes effect only between a season's last round and
 * the next season's first.
 */
class OffSeasonOnly extends RuntimeException {}
