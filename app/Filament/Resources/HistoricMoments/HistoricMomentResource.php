<?php

namespace App\Filament\Resources\HistoricMoments;

use App\Filament\Resources\HistoricMoments\Pages\CreateHistoricMoment;
use App\Filament\Resources\HistoricMoments\Pages\EditHistoricMoment;
use App\Filament\Resources\HistoricMoments\Pages\ListHistoricMoments;
use App\Filament\Resources\HistoricMoments\Schemas\HistoricMomentForm;
use App\Filament\Resources\HistoricMoments\Tables\HistoricMomentsTable;
use App\Models\HistoricMoment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HistoricMomentResource extends Resource
{
    protected static ?string $model = HistoricMoment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return HistoricMomentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HistoricMomentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricMoments::route('/'),
            'create' => CreateHistoricMoment::route('/create'),
            'edit' => EditHistoricMoment::route('/{record}/edit'),
        ];
    }
}
