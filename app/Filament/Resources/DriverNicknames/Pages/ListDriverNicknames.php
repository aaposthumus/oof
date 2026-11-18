<?php

namespace App\Filament\Resources\DriverNicknames\Pages;

use App\Filament\Resources\DriverNicknames\DriverNicknameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDriverNicknames extends ListRecords
{
    protected static string $resource = DriverNicknameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
