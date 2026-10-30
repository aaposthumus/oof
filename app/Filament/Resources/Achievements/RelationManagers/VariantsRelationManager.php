<?php

namespace App\Filament\Resources\Achievements\RelationManagers;

use App\Models\Driver;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A driver's own name for the achievement, e.g. Overstepper's "Off Course".
 * It counts as the same achievement; the next run puts it on the awards.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'family_name')
                    ->getOptionLabelFromRecordUsing(fn (Driver $record): string => "{$record->given_name} {$record->family_name}")
                    ->searchable(['given_name', 'family_name'])
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('driver.family_name')
                    ->label('Driver')
                    ->state(fn ($record): string => "{$record->driver->given_name} {$record->driver->family_name}"),
                TextColumn::make('name'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
