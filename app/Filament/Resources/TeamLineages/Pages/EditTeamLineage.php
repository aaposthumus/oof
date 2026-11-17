<?php

namespace App\Filament\Resources\TeamLineages\Pages;

use App\Filament\Resources\TeamLineages\TeamLineageResource;
use App\Models\TeamLineage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTeamLineage extends EditRecord
{
    protected static string $resource = TeamLineageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (TeamLineage $record): bool => TeamLineageResource::applying(fn () => (bool) $record->delete())),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return TeamLineageResource::applying(fn () => parent::handleRecordUpdate($record, $data));
    }
}
