<?php

namespace App\Filament\Resources\DriverNicknames\Tables;

use App\Enums\NicknameStatus;
use App\Models\DriverNickname;
use App\Models\NicknameEvolution;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverNicknamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['driver', 'evolvesFrom']))
            ->defaultSort('driver_id')
            ->columns([
                TextColumn::make('driver.family_name')
                    ->label('Driver')
                    ->formatStateUsing(fn (DriverNickname $record): string => "{$record->driver->given_name} {$record->driver->family_name}")
                    ->searchable(['given_name', 'family_name'])
                    ->sortable(),
                TextColumn::make('nickname')
                    ->description(fn (DriverNickname $record): ?string => $record->evolvesFrom === null ? null : "Evolves from {$record->evolvesFrom->nickname}")
                    ->searchable(),
                TextColumn::make('motto')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('stage'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (NicknameStatus $state): string => $state->label())
                    ->color(fn (NicknameStatus $state): string => match ($state) {
                        NicknameStatus::Active => 'success',
                        NicknameStatus::Planned => 'info',
                        NicknameStatus::Retired => 'gray',
                    }),
                IconColumn::make('is_draft')
                    ->label('Draft')
                    ->boolean(),
                TextColumn::make('active_from')
                    ->label('In use')
                    ->state(fn (DriverNickname $record): ?string => match (true) {
                        $record->active_from === null && $record->active_to === null => null,
                        default => ($record->active_from?->toDateString() ?? '…').' – '.($record->active_to?->toDateString() ?? '…'),
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(NicknameStatus::cases())->mapWithKeys(fn (NicknameStatus $status) => [$status->value => $status->label()])->all()),
                TernaryFilter::make('is_draft')
                    ->label('Draft'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Correct'),
                // Only a stage nothing builds on: no next stage and no evolution from or to it.
                DeleteAction::make()
                    ->visible(fn (DriverNickname $record): bool => ! $record->nextStage()->exists()
                        && ! NicknameEvolution::query()->where('from_nickname_id', $record->id)->orWhere('to_nickname_id', $record->id)->exists()),
            ]);
    }
}
