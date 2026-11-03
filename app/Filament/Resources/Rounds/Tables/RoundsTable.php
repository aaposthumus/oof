<?php

namespace App\Filament\Resources\Rounds\Tables;

use App\Enums\SessionType;
use App\Filament\Resources\Rounds\RoundResource;
use App\Models\Round;
use App\Models\Season;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['season', 'circuit'])
                ->withExists([
                    'raceSessions as has_race_results' => fn (Builder $sessions) => $sessions->where('type', SessionType::Race)->whereHas('driverEntries.result'),
                    'raceSessions as has_qualifying' => fn (Builder $sessions) => $sessions->where('type', SessionType::Qualifying)->whereHas('driverEntries.qualifyingResult'),
                    'raceSessions as has_openf1_data' => fn (Builder $sessions) => $sessions->where(fn (Builder $data) => $data->whereHas('raceControlMessages')->orWhereHas('sessionLaps')),
                ]))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('date')->orderByDesc('round_number'))
            ->columns([
                TextColumn::make('season.year')->label('Season'),
                TextColumn::make('round_number')->label('Round'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('circuit.name')->label('Circuit'),
                TextColumn::make('date')->date(),
                IconColumn::make('has_race_results')->label('Race results')->boolean(),
                IconColumn::make('has_qualifying')->label('Qualifying')->boolean(),
                IconColumn::make('has_openf1_data')->label('OpenF1')->boolean(),
            ])
            ->filters([
                SelectFilter::make('season')
                    ->relationship('season', 'year', fn (Builder $query) => $query->orderByDesc('year'))
                    ->default(fn (): ?int => Season::currentId()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Round $record): string => RoundResource::getUrl('view', ['record' => $record]));
    }
}
