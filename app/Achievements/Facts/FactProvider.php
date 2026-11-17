<?php

namespace App\Achievements\Facts;

use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * Builds one type of fact for a session.
 */
interface FactProvider extends DataNeeds
{
    /**
     * @return Collection<int, Fact>
     */
    public function facts(SessionData $data): Collection;
}
