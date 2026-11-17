<?php

namespace App\Filament\Resources\MomentSensitivities\Tables;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Enums\SensitivitySource;
use App\Models\MomentSensitivity;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
                    ->color(fn (SensitivitySeverity $state): string => match ($state) {
                        SensitivitySeverity::Fatal => 'danger',
                        SensitivitySeverity::Injury => 'warning',
                        SensitivitySeverity::Minor => 'gray',
                    }),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (SensitivitySource $state): string => ucfirst($state->value))
                    ->color(fn (SensitivitySource $state): string => $state === SensitivitySource::Engine ? 'info' : 'gray'),
                TextColumn::make('decision')
                    ->label('Status')
                    ->state(fn (MomentSensitivity $record): string => match (true) {
                        $record->dismissed_at !== null => 'Dismissed',
                        $record->isProposal() => 'Proposed: acts as sensitive',
                        $record->confirmed_at !== null => 'Confirmed',
                        default => 'In force',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Dismissed' => 'gray',
                        'Proposed: acts as sensitive' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('note')
                    ->limit(80)
                    ->wrap(),
            ])
            ->filters([
                Filter::make('proposals')
                    ->label('Open engine proposals')
                    ->query(fn (Builder $query) => $query->where('source', SensitivitySource::Engine)->whereNull('confirmed_at')->whereNull('dismissed_at')),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (MomentSensitivity $record): bool => $record->source === SensitivitySource::Engine && $record->confirmed_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('The weekend stays sensitive for good.')
                    ->action(fn (MomentSensitivity $record) => $record->confirm()),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (MomentSensitivity $record): bool => $record->source === SensitivitySource::Engine && $record->dismissed_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('The proposal no longer counts and is not proposed again. Awards already flagged keep their flag; withheld ones the engine left out come back on the next run.')
                    ->action(fn (MomentSensitivity $record) => $record->dismiss()),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
