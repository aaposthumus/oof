<?php

namespace App\Filament\Resources\ExcavationRuns\Tables;

use App\Excavation\Support\EvidenceSummary;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExcavationRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('excavator')
                    ->formatStateUsing(fn (string $state): string => EvidenceSummary::typeLabel($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('excavator_version')
                    ->label('Version')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not finished'),
                TextColumn::make('candidates_found')
                    ->numeric()
                    ->sortable(),
            ]);
    }
}
