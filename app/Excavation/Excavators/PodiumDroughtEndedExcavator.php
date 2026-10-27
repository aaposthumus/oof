<?php

namespace App\Excavation\Excavators;

/**
 * A driver's first podium after the most starts without one.
 */
class PodiumDroughtEndedExcavator extends DroughtEndedExcavator
{
    public function key(): string
    {
        return 'podium_drought_ended';
    }

    protected function ends(object $row): bool
    {
        return $row->is_podium;
    }
}
