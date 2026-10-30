<?php

namespace App\Filament\Resources\AchievementAwards;

use App\Filament\Resources\AchievementAwards\Pages\ListAchievementAwards;
use App\Filament\Resources\AchievementAwards\Pages\ViewAchievementAward;
use App\Filament\Resources\AchievementAwards\Schemas\AchievementAwardInfolist;
use App\Filament\Resources\AchievementAwards\Tables\AchievementAwardsTable;
use App\Models\AchievementAward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AchievementAwardResource extends Resource
{
    protected static ?string $model = AchievementAward::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Awards';

    protected static ?int $navigationSort = 5;

    public static function infolist(Schema $schema): Schema
    {
        return AchievementAwardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AchievementAwardsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAchievementAwards::route('/'),
            'view' => ViewAchievementAward::route('/{record}'),
        ];
    }
}
