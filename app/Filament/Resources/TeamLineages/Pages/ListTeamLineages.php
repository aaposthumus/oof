<?php

namespace App\Filament\Resources\TeamLineages\Pages;

use App\Filament\Resources\TeamLineages\TeamLineageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamLineages extends ListRecords
{
    protected static string $resource = TeamLineageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
