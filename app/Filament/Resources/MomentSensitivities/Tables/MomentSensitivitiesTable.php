<?php

namespace App\Filament\Resources\MomentSensitivities\Tables;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MomentSensitivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['round.season', 'driver']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('round.name')
                    ->label('Round')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->round->season->year} {$state}"),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->state(fn ($record): ?string => $record->driver ? "{$record->driver->given_name} {$record->driver->family_name}" : null)
                    ->placeholder('Whole round'),
                TextColumn::make('level')
                    ->badge()
                    ->formatStateUsing(fn (SensitivityLevel $state): string => ucfirst($state->value))
                    ->color(fn (SensitivityLevel $state): string => $state === SensitivityLevel::Blocked ? 'danger' : 'warning'),
                TextColumn::make('severity')
                    ->badge()
                    ->formatStateUsing(fn (SensitivitySeverity $state): string => ucfirst($state->value))
                    ->color(fn (SensitivitySeverity $state): string => $state === SensitivitySeverity::Fatal ? 'danger' : 'warning'),
                TextColumn::make('note')
                    ->limit(80)
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
