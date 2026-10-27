<?php

namespace App\Filament\Resources\HistoricMoments\Pages;

use App\Filament\Resources\HistoricMoments\HistoricMomentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHistoricMoments extends ListRecords
{
    protected static string $resource = HistoricMomentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
