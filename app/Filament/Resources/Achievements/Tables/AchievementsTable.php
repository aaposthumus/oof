<?php

namespace App\Filament\Resources\Achievements\Tables;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\SessionType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AchievementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('awards'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')
                    ->description(fn ($record): string => $record->description)
                    ->searchable(),
                TextColumn::make('shelf')
                    ->badge()
                    ->formatStateUsing(fn (AchievementShelf $state): string => $state->label()),
                TextColumn::make('section')
                    ->formatStateUsing(fn (AchievementSection $state): string => $state->label()),
                TextColumn::make('tier')
                    ->formatStateUsing(fn (?AchievementTier $state): string => $state?->label() ?? '–')
                    ->sortable(),
                TextColumn::make('points')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('session_types')
                    ->label('Sessions')
                    ->formatStateUsing(fn (string $state): string => SessionType::from($state)->label())
                    ->badge(),
                IconColumn::make('stacks')
                    ->boolean(),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('awards_count')
                    ->label('Awards')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('shelf')
                    ->options(collect(AchievementShelf::cases())->mapWithKeys(fn (AchievementShelf $shelf) => [$shelf->value => $shelf->label()])->all()),
                SelectFilter::make('section')
                    ->options(collect(AchievementSection::cases())->mapWithKeys(fn (AchievementSection $section) => [$section->value => $section->label()])->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
