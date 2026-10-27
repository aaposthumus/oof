<?php

namespace App\Filament\Resources\HistoricMoments\Schemas;

use App\Enums\HistoricMomentStatus;
use App\Filament\Resources\MomentCandidates\MomentCandidateResource;
use App\Filament\Support\SensitiveNotice;
use App\Models\HistoricMoment;
use App\Models\Round;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HistoricMomentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SensitiveNotice::make(
                    fn (?HistoricMoment $record): bool => $record?->sensitivityNote() !== null,
                    fn (HistoricMoment $record): ?string => $record->sensitivityNote(),
                ),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('story')
                    ->rows(12)
                    ->columnSpanFull(),
                TagsInput::make('tags')
                    ->columnSpanFull(),
                TextInput::make('era')
                    ->required()
                    ->maxLength(32)
                    ->helperText('For example 1950s.'),
                Select::make('status')
                    ->options([
                        HistoricMomentStatus::Draft->value => 'Draft',
                        HistoricMomentStatus::Published->value => 'Published',
                    ])
                    ->default(HistoricMomentStatus::Draft->value)
                    ->required(),
                Select::make('round_id')
                    ->label('Round')
                    ->relationship('round', 'name', fn ($query) => $query->with('season'))
                    ->getOptionLabelFromRecordUsing(fn (Round $record): string => "{$record->season->year} {$record->name}")
                    ->searchable(['name'])
                    ->required()
                    ->disabled(fn (?HistoricMoment $record): bool => $record !== null)
                    ->dehydrated(),
                DatePicker::make('date')
                    ->required(),
                TextEntry::make('candidate_link')
                    ->label('Candidate')
                    ->state(fn (HistoricMoment $record): string => "Candidate #{$record->moment_candidate_id}")
                    ->url(fn (HistoricMoment $record): string => MomentCandidateResource::getUrl('view', ['record' => $record->moment_candidate_id]))
                    ->visible(fn (?HistoricMoment $record): bool => $record?->moment_candidate_id !== null),
            ])
            ->columns(2);
    }
}
