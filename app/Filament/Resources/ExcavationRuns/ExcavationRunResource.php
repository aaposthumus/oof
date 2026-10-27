<?php

namespace App\Filament\Resources\ExcavationRuns;

use App\Filament\Resources\ExcavationRuns\Pages\ListExcavationRuns;
use App\Filament\Resources\ExcavationRuns\Tables\ExcavationRunsTable;
use App\Models\ExcavationRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExcavationRunResource extends Resource
{
    protected static ?string $model = ExcavationRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return ExcavationRunsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExcavationRuns::route('/'),
        ];
    }
}
