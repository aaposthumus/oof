<?php

namespace App\Filament\Resources\Rounds\Pages;

use App\Filament\Resources\Rounds\RoundResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewRound extends ViewRecord
{
    protected static string $resource = RoundResource::class;

    public function getTitle(): string|Htmlable
    {
        return RoundResource::getRecordTitle($this->getRecord()) ?? parent::getTitle();
    }
}
