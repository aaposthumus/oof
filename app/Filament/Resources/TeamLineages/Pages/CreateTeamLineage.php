<?php

namespace App\Filament\Resources\TeamLineages\Pages;

use App\Filament\Resources\TeamLineages\TeamLineageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTeamLineage extends CreateRecord
{
    protected static string $resource = TeamLineageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return TeamLineageResource::applying(fn () => parent::handleRecordCreation($data));
    }
}
