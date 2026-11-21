<?php

namespace App\Filament\Resources\NationalityCountries\Pages;

use App\Filament\Resources\NationalityCountries\NationalityCountryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNationalityCountry extends EditRecord
{
    protected static string $resource = NationalityCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
