<?php

namespace App\Filament\Resources\MomentCandidates;

use App\Filament\Resources\MomentCandidates\Pages\ListMomentCandidates;
use App\Filament\Resources\MomentCandidates\Pages\ViewMomentCandidate;
use App\Filament\Resources\MomentCandidates\Schemas\MomentCandidateInfolist;
use App\Filament\Resources\MomentCandidates\Tables\MomentCandidatesTable;
use App\Models\MomentCandidate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MomentCandidateResource extends Resource
{
    protected static ?string $model = MomentCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return MomentCandidateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MomentCandidatesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMomentCandidates::route('/'),
            'view' => ViewMomentCandidate::route('/{record}'),
        ];
    }
}
