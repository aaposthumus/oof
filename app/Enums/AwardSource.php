<?php

namespace App\Enums;

/**
 * Where an award came from: the engine, a person in the CMS, or a curated list.
 */
enum AwardSource: string
{
    case Engine = 'engine';
    case Manual = 'manual';
    case Curated = 'curated';
}
