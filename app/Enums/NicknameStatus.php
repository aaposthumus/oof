<?php

namespace App\Enums;

/**
 * Where a nickname stage stands: planned (a next stage waiting for its
 * evolution), active (in use) or retired (replaced by its next stage).
 */
enum NicknameStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
