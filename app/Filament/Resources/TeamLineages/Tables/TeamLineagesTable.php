<?php

namespace App\Filament\Resources\TeamLineages\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamLineagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('team'))
            ->defaultSort('team_slug')
            ->columns([
                TextColumn::make('team_slug')
                    ->label('Lasting team')
                    ->formatStateUsing(fn ($state, $record): string => $record->team ? "{$record->team->name} ({$state})" : $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jolpica_constructor_id')
                    ->label('Constructor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('seasons')
                    ->state(fn ($record): string => match (true) {
                        $record->from_year === null && $record->to_year === null => 'All',
                        $record->from_year === null => "Until {$record->to_year}",
                        $record->to_year === null => "From {$record->from_year}",
                        default => "{$record->from_year}–{$record->to_year}",
                    }),
                TextColumn::make('note')
                    ->limit(80)
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
