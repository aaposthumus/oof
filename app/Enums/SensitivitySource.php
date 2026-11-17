<?php

namespace App\Enums;

/**
 * Where a sensitivity came from. An engine one is a proposal until a person confirms or dismisses it.
 */
enum SensitivitySource: string
{
    case Seed = 'seed';
    case Manual = 'manual';
    case Engine = 'engine';
}
