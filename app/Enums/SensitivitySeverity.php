<?php

namespace App\Enums;

enum SensitivitySeverity: string
{
    case Fatal = 'fatal';
    case Injury = 'injury';
    case Minor = 'minor';
}
