<?php

namespace App\Achievements\Facts;

/**
 * A fact provider whose sources are not all read from every session it is
 * built in (career firsts read race results in a race and qualifying in
 * qualifying, and the earlier rounds of both). It names the sources that
 * must have rows in the session itself; without this, all its sources must.
 */
interface GatesSessions
{
    /**
     * @return list<DataSource>
     */
    public function sessionSources(): array;
}
