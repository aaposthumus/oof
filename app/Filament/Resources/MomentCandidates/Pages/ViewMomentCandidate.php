<?php

namespace App\Filament\Resources\MomentCandidates\Pages;

use App\Filament\Resources\MomentCandidates\MomentCandidateResource;
use App\Filament\Resources\MomentCandidates\Tables\MomentCandidatesTable;
use Filament\Resources\Pages\ViewRecord;

class ViewMomentCandidate extends ViewRecord
{
    protected static string $resource = MomentCandidateResource::class;

    protected function getHeaderActions(): array
    {
        return MomentCandidatesTable::reviewActions();
    }
}
