<?php

namespace App\Filament\Resources\NationalityCountries\Pages;

use App\Filament\Resources\NationalityCountries\NationalityCountryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNationalityCountries extends ListRecords
{
    protected static string $resource = NationalityCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
