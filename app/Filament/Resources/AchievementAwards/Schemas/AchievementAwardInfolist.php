<?php

namespace App\Filament\Resources\AchievementAwards\Schemas;

use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Filament\Resources\AchievementAwards\Tables\AchievementAwardsTable;
use App\Models\AchievementAward;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class AchievementAwardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Award')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Achievement')
                            ->state(fn (AchievementAward $record): string => $record->title()),
                        TextEntry::make('session')
                            ->state(fn (AchievementAward $record): string => AchievementAwardsTable::sessionTitle($record)),
                        TextEntry::make('driver_name')
                            ->label('Driver')
                            ->state(fn (AchievementAward $record): ?string => $record->driver ? "{$record->driver->given_name} {$record->driver->family_name}" : null)
                            ->placeholder('No car'),
                        TextEntry::make('confidence')
                            ->badge()
                            ->formatStateUsing(fn (AwardConfidence $state): string => $state->label()),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (AwardStatus $state): string => $state->label()),
                        TextEntry::make('review_reason')
                            ->label('Waiting for')
                            ->formatStateUsing(fn (?AwardReviewReason $state): ?string => $state?->label())
                            ->placeholder('–'),
                        TextEntry::make('subtitle')
                            ->placeholder('–'),
                        TextEntry::make('achievement.description')
                            ->label('Trigger'),
                        TextEntry::make('decision')
                            ->state(fn (AchievementAward $record): ?string => $record->isDecided()
                                ? "{$record->status->label()} by {$record->decider?->name} on {$record->decided_at->format('j M Y H:i')}"
                                : null)
                            ->placeholder('Not decided by a person')
                            ->helperText(fn (AchievementAward $record): ?string => $record->decision_note),
                    ])
                    ->columnSpanFull(),
                Section::make('Evidence')
                    ->description(fn (AchievementAward $record): string => match ($record->evidence['source'] ?? null) {
                        'race_control' => 'Race control messages, word for word.',
                        'jolpica' => "From Jolpica's results.",
                        'openf1' => "From OpenF1's timing data.",
                        default => 'Source data.',
                    })
                    ->schema([
                        TextEntry::make('evidence_note')
                            ->hiddenLabel()
                            ->state(fn (AchievementAward $record): ?string => $record->evidence['note'] ?? null)
                            ->visible(fn (AchievementAward $record): bool => isset($record->evidence['note'])),
                        RepeatableEntry::make('evidence_messages')
                            ->hiddenLabel()
                            ->state(fn (AchievementAward $record): array => $record->evidence['messages'] ?? [])
                            ->visible(fn (AchievementAward $record): bool => ($record->evidence['messages'] ?? []) !== [])
                            ->columns(4)
                            ->schema([
                                TextEntry::make('issued_at')->label('Time (UTC)'),
                                TextEntry::make('lap')->placeholder('–'),
                                TextEntry::make('message')
                                    ->fontFamily(FontFamily::Mono)
                                    ->columnSpan(2),
                            ]),
                        RepeatableEntry::make('evidence_rows')
                            ->hiddenLabel()
                            ->state(fn (AchievementAward $record): array => array_map(fn (array $row) => ['row' => array_map(fn ($value) => (string) $value, $row)], $record->evidence['rows'] ?? []))
                            ->visible(fn (AchievementAward $record): bool => ($record->evidence['rows'] ?? []) !== [])
                            ->schema([
                                KeyValueEntry::make('row')
                                    ->hiddenLabel()
                                    ->keyLabel('Field')
                                    ->valueLabel('Value'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
