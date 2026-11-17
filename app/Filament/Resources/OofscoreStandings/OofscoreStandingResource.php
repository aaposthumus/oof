<?php

namespace App\Filament\Resources\OofscoreStandings;

use App\Filament\Resources\OofscoreStandings\Pages\ListOofscoreStandings;
use App\Filament\Resources\OofscoreStandings\Tables\OofscoreStandingsTable;
use App\Models\OofscoreStanding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The OOFscore leaderboard after a round: a read-only projection of the
 * final awards, rebuilt by the engine and after every decision.
 */
class OofscoreStandingResource extends Resource
{
    protected static ?string $model = OofscoreStanding::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'OOFscore';

    protected static ?string $modelLabel = 'OOFscore standing';

    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return OofscoreStandingsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOofscoreStandings::route('/'),
        ];
    }
}
