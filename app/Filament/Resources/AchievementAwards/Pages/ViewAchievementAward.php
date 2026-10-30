<?php

namespace App\Filament\Resources\AchievementAwards\Pages;

use App\Filament\Resources\AchievementAwards\AchievementAwardResource;
use App\Filament\Resources\AchievementAwards\Tables\AchievementAwardsTable;
use Filament\Resources\Pages\ViewRecord;

class ViewAchievementAward extends ViewRecord
{
    protected static string $resource = AchievementAwardResource::class;

    protected function getHeaderActions(): array
    {
        return AchievementAwardsTable::reviewActions();
    }
}
