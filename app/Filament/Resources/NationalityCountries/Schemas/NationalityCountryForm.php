<?php

namespace App\Filament\Resources\NationalityCountries\Schemas;

use App\Models\Circuit;
use App\Models\Driver;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NationalityCountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nationality')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->datalist(fn (): array => Driver::query()->whereNotNull('nationality')->distinct()->orderBy('nationality')->pluck('nationality')->all())
                    ->helperText('As Jolpica writes it, for example "British".'),
                TextInput::make('country')
                    ->label('At home in')
                    ->required()
                    ->maxLength(64)
                    ->datalist(fn (): array => Circuit::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country')->all())
                    ->helperText('The circuit country as Jolpica writes it, for example "UK" or "USA".'),
                TextInput::make('note')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
