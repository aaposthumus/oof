<?php

namespace App\Filament\Resources\MomentSensitivities\Pages;

use App\Filament\Resources\MomentSensitivities\MomentSensitivityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMomentSensitivities extends ListRecords
{
    protected static string $resource = MomentSensitivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
