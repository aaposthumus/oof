<?php

namespace App\Filament\Resources\DriverNicknames\Pages;

use App\Filament\Resources\DriverNicknames\DriverNicknameResource;
use Filament\Resources\Pages\EditRecord;

/**
 * A correction: the text, motto and draft flag change in place, with no new
 * stage and no event.
 */
class EditDriverNickname extends EditRecord
{
    protected static string $resource = DriverNicknameResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'evolution_trigger' => ($data['evolution_trigger'] ?? []) === [] ? null : $data['evolution_trigger']];
    }
}
