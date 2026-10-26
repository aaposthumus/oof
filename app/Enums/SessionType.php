<?php

namespace App\Enums;

enum SessionType: string
{
    case Race = 'race';
    case Qualifying = 'qualifying';
    case Sprint = 'sprint';
    case SprintQualifying = 'sprint_qualifying';
    case Practice1 = 'practice_1';
    case Practice2 = 'practice_2';
    case Practice3 = 'practice_3';
}
