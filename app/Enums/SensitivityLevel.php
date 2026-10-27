<?php

namespace App\Enums;

enum SensitivityLevel: string
{
    case Sensitive = 'sensitive';
    case Blocked = 'blocked';
}
