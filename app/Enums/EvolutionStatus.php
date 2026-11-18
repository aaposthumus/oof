<?php

namespace App\Enums;

/**
 * Proposed by the engine, approved or dismissed by a person, applied on its
 * effective date.
 */
enum EvolutionStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Applied = 'applied';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
