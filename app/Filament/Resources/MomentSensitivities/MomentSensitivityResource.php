<?php

namespace App\Filament\Resources\MomentSensitivities;

use App\Filament\Resources\MomentSensitivities\Pages\CreateMomentSensitivity;
use App\Filament\Resources\MomentSensitivities\Pages\EditMomentSensitivity;
use App\Filament\Resources\MomentSensitivities\Pages\ListMomentSensitivities;
use App\Filament\Resources\MomentSensitivities\Schemas\MomentSensitivityForm;
use App\Filament\Resources\MomentSensitivities\Tables\MomentSensitivitiesTable;
use App\Models\MomentSensitivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MomentSensitivityResource extends Resource
{
    protected static ?string $model = MomentSensitivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MomentSensitivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MomentSensitivitiesTable::configure($table);
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
            'index' => ListMomentSensitivities::route('/'),
            'create' => CreateMomentSensitivity::route('/create'),
            'edit' => EditMomentSensitivity::route('/{record}/edit'),
        ];
    }
}
