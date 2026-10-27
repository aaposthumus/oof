<?php

namespace App\Excavation\Excavators;

/**
 * A driver's first win after the most starts without one.
 */
class WinDroughtEndedExcavator extends DroughtEndedExcavator
{
    public function key(): string
    {
        return 'win_drought_ended';
    }

    protected function ends(object $row): bool
    {
        return $row->is_win;
    }
}
