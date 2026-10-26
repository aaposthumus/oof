<?php

namespace App\Normalizers\Jolpica;

use App\Enums\SessionType;

class SprintResultsNormalizer extends ResultsNormalizer
{
    public function endpoint(): string
    {
        return 'sprint';
    }

    protected function sessionType(): SessionType
    {
        return SessionType::Sprint;
    }

    protected function resultsKey(): string
    {
        return 'SprintResults';
    }
}
