<?php

namespace App\Filament\Resources\NationalityCountries;

use App\Filament\Resources\NationalityCountries\Pages\CreateNationalityCountry;
use App\Filament\Resources\NationalityCountries\Pages\EditNationalityCountry;
use App\Filament\Resources\NationalityCountries\Pages\ListNationalityCountries;
use App\Filament\Resources\NationalityCountries\Schemas\NationalityCountryForm;
use App\Filament\Resources\NationalityCountries\Tables\NationalityCountriesTable;
use App\Models\NationalityCountry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Which country each driver nationality is at home in, for Hometown Hero.
 * The rows are part of the catalogue version, so after a change the hourly
 * oof:achievements --stale re-runs what they decide.
 */
class NationalityCountryResource extends Resource
{
    protected static ?string $model = NationalityCountry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Nationalities';

    protected static ?string $modelLabel = 'nationality';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return NationalityCountryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NationalityCountriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNationalityCountries::route('/'),
            'create' => CreateNationalityCountry::route('/create'),
            'edit' => EditNationalityCountry::route('/{record}/edit'),
        ];
    }
}
