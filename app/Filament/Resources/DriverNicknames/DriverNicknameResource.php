<?php

namespace App\Filament\Resources\DriverNicknames;

use App\Filament\Resources\DriverNicknames\Pages\CreateDriverNickname;
use App\Filament\Resources\DriverNicknames\Pages\EditDriverNickname;
use App\Filament\Resources\DriverNicknames\Pages\ListDriverNicknames;
use App\Filament\Resources\DriverNicknames\Schemas\DriverNicknameForm;
use App\Filament\Resources\DriverNicknames\Tables\DriverNicknamesTable;
use App\Models\DriverNickname;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Drivers' nicknames and mottos, one row per stage. Editing a row is a
 * correction: it shows everywhere at once. A new stage is planned here and
 * becomes active only through an approved evolution (Nickname evolutions).
 */
class DriverNicknameResource extends Resource
{
    protected static ?string $model = DriverNickname::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Nicknames';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return DriverNicknameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverNicknamesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverNicknames::route('/'),
            'create' => CreateDriverNickname::route('/create'),
            'edit' => EditDriverNickname::route('/{record}/edit'),
        ];
    }
}
