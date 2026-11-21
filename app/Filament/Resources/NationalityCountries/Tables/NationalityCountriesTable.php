<?php

namespace App\Filament\Resources\NationalityCountries\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NationalityCountriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nationality')
            ->columns([
                TextColumn::make('nationality')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('At home in')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('note')
                    ->limit(80)
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
