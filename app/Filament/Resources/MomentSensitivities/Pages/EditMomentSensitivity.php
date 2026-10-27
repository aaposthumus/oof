<?php

namespace App\Filament\Resources\MomentSensitivities\Pages;

use App\Filament\Resources\MomentSensitivities\MomentSensitivityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMomentSensitivity extends EditRecord
{
    protected static string $resource = MomentSensitivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
