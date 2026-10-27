<?php

namespace App\Filament\Resources\HistoricMoments\Tables;

use App\Enums\HistoricMomentStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HistoricMomentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('round.season'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('era')
                    ->sortable(),
                TextColumn::make('round.name')
                    ->label('Round')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->round->season->year} {$state}"),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (HistoricMomentStatus $state): string => ucfirst($state->value))
                    ->color(fn (HistoricMomentStatus $state): string => $state === HistoricMomentStatus::Published ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
