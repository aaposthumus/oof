<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;

/**
 * A named condition on a fact, for a trigger's where list.
 */
interface Predicate extends DataNeeds
{
    public function test(Fact $fact, SessionData $data): bool;
}
