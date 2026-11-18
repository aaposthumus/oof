<?php

namespace App\Filament\Resources\NicknameEvolutions;

use App\Filament\Resources\NicknameEvolutions\Pages\ListNicknameEvolutions;
use App\Filament\Resources\NicknameEvolutions\Tables\NicknameEvolutionsTable;
use App\Models\NicknameEvolution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Evolutions the engine proposed after a breakthrough: approve one with an
 * off-season effective date, or dismiss it. Applied on that date by
 * oof:nicknames:evolve.
 */
class NicknameEvolutionResource extends Resource
{
    protected static ?string $model = NicknameEvolution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Nickname evolutions';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NicknameEvolutionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNicknameEvolutions::route('/'),
        ];
    }
}
