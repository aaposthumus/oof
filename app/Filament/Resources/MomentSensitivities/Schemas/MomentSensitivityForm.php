<?php

namespace App\Filament\Resources\MomentSensitivities\Schemas;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Models\Driver;
use App\Models\Round;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MomentSensitivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('round_id')
                    ->label('Round')
                    ->relationship('round', 'name', fn ($query) => $query->with('season')->orderByDesc('date'))
                    ->getOptionLabelFromRecordUsing(fn (Round $record): string => "{$record->season->year} {$record->name}")
                    ->searchable(['name'])
                    ->preload()
                    ->required(),
                Select::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'family_name')
                    ->getOptionLabelFromRecordUsing(fn (Driver $record): string => "{$record->given_name} {$record->family_name}")
                    ->searchable(['given_name', 'family_name'])
                    ->helperText('Leave empty to cover the whole round.'),
                Select::make('level')
                    ->options([
                        SensitivityLevel::Sensitive->value => 'Sensitive: keep candidates, flag them for review',
                        SensitivityLevel::Blocked->value => 'Blocked: excavation skips it',
                    ])
                    ->default(SensitivityLevel::Sensitive->value)
                    ->required(),
                Select::make('severity')
                    ->options([
                        SensitivitySeverity::Fatal->value => 'Fatal: someone was killed',
                        SensitivitySeverity::Injury->value => 'Injury: someone was seriously injured',
                        SensitivitySeverity::Minor->value => 'Minor: precautionary checks, no lasting injury',
                    ])
                    ->default(SensitivitySeverity::Fatal->value)
                    ->helperText('Stories spanning a season, such as a title decider, only flag fatal entries and injuries to the drivers involved; minor ones never leave their round.')
                    ->required(),
                Textarea::make('note')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
