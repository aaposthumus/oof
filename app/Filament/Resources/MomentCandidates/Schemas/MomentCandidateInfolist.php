<?php

namespace App\Filament\Resources\MomentCandidates\Schemas;

use App\Excavation\Support\EvidenceSummary;
use App\Filament\Support\SensitiveNotice;
use App\Models\MomentCandidate;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class MomentCandidateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                SensitiveNotice::make(
                    fn (MomentCandidate $record): bool => $record->is_sensitive,
                    fn (MomentCandidate $record): ?string => $record->sensitivity_note,
                ),
                Section::make('Candidate')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('type')
                            ->formatStateUsing(fn (string $state): string => EvidenceSummary::typeLabel($state)),
                        TextEntry::make('score')
                            ->label('Score (percentile in type)')
                            ->numeric(1),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => ucfirst($state->value)),
                        TextEntry::make('round_title')
                            ->label('Round')
                            ->state(fn (MomentCandidate $record): string => "{$record->round->season->year} {$record->round->name}"),
                        TextEntry::make('driver_name')
                            ->label('Driver')
                            ->state(fn (MomentCandidate $record): ?string => $record->driver ? "{$record->driver->given_name} {$record->driver->family_name}" : null)
                            ->placeholder('Whole round'),
                        TextEntry::make('excavationRun.excavator')
                            ->label('Found by')
                            ->formatStateUsing(fn ($state, MomentCandidate $record): string => EvidenceSummary::typeLabel($state)." v{$record->excavationRun->excavator_version}"),
                    ])
                    ->columnSpanFull(),
                Section::make('Evidence')
                    ->schema([
                        TextEntry::make('evidence_summary')
                            ->hiddenLabel()
                            ->state(fn (MomentCandidate $record): string => EvidenceSummary::summary($record))
                            ->size(TextSize::Large),
                        KeyValueEntry::make('evidence_details')
                            ->hiddenLabel()
                            ->keyLabel('Measure')
                            ->valueLabel('Value')
                            ->state(fn (MomentCandidate $record): array => EvidenceSummary::details($record)),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
