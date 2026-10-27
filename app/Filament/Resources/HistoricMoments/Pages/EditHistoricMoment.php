<?php

namespace App\Filament\Resources\HistoricMoments\Pages;

use App\Filament\Resources\HistoricMoments\HistoricMomentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHistoricMoment extends EditRecord
{
    protected static string $resource = HistoricMomentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
