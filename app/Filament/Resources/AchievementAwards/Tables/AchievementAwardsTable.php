<?php

namespace App\Filament\Resources\AchievementAwards\Tables;

use App\Enums\AchievementShelf;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\Season;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AchievementAwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['raceSession.round.season', 'driver', 'achievement']))
            ->defaultSort('race_session_id', 'desc')
            ->defaultGroup('raceSession.id')
            ->groups([
                Group::make('raceSession.id')
                    ->label('Session')
                    ->getTitleFromRecordUsing(fn (AchievementAward $record): string => self::sessionTitle($record))
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('race_session_id', $direction)),
            ])
            ->columns([
                TextColumn::make('achievement.name')
                    ->label('Achievement')
                    ->state(fn (AchievementAward $record): string => $record->title())
                    ->description(fn (AchievementAward $record): ?string => $record->subtitle),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->state(fn (AchievementAward $record): ?string => $record->driver ? "{$record->driver->given_name} {$record->driver->family_name}" : null)
                    ->placeholder(fn (AchievementAward $record): string => $record->achievement->shelf === AchievementShelf::SafetyCar ? 'Safety Car' : 'No car yet'),
                TextColumn::make('achievement.shelf')
                    ->label('Shelf')
                    ->formatStateUsing(fn (AchievementShelf $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('confidence')
                    ->badge()
                    ->formatStateUsing(fn (AwardConfidence $state): string => $state->label())
                    ->color(fn (AwardConfidence $state): string => match ($state) {
                        AwardConfidence::High => 'success',
                        AwardConfidence::Medium => 'warning',
                        AwardConfidence::Low => 'danger',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AwardStatus $state): string => $state->label())
                    ->color(fn (AwardStatus $state): string => match ($state) {
                        AwardStatus::Pending => 'info',
                        AwardStatus::Final => 'success',
                        AwardStatus::Suppressed => 'gray',
                    }),
                TextColumn::make('review_reason')
                    ->label('Review')
                    ->formatStateUsing(fn (?AwardReviewReason $state): ?string => $state?->label())
                    ->placeholder('–'),
                TextColumn::make('decision_note')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('season')
                    ->options(fn (): array => Season::query()->where('year', '>=', config('achievements.first_season'))->orderByDesc('year')->pluck('year', 'year')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, $year) => $query->whereHas('raceSession.round.season', fn (Builder $season) => $season->where('year', $year)))),
                Filter::make('round')
                    ->schema([TextInput::make('round_number')->label('Round')->numeric()->minValue(1)])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['round_number'] ?? null, fn (Builder $query, $round) => $query->whereHas('raceSession.round', fn (Builder $q) => $q->where('round_number', $round)))),
                SelectFilter::make('session')
                    ->options(collect(SessionType::cases())->mapWithKeys(fn (SessionType $type) => [$type->value => $type->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, $type) => $query->whereHas('raceSession', fn (Builder $q) => $q->where('type', $type)))),
                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'family_name')
                    ->getOptionLabelFromRecordUsing(fn (Driver $record): string => "{$record->given_name} {$record->family_name}")
                    ->searchable(['given_name', 'family_name']),
                SelectFilter::make('achievement_id')
                    ->label('Achievement')
                    ->relationship('achievement', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('confidence')
                    ->options(collect(AwardConfidence::cases())->mapWithKeys(fn (AwardConfidence $confidence) => [$confidence->value => $confidence->label()])->all()),
                SelectFilter::make('status')
                    ->options(collect(AwardStatus::cases())->mapWithKeys(fn (AwardStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('review_reason')
                    ->label('Review reason')
                    ->options(collect(AwardReviewReason::cases())->mapWithKeys(fn (AwardReviewReason $reason) => [$reason->value => $reason->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ...self::reviewActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suppress')
                        ->label('Suppress')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->schema([self::reasonField()])
                        ->hidden(fn ($livewire): bool => $livewire->activeStatus() === AwardStatus::Suppressed)
                        ->action(fn (Collection $records, array $data) => $records
                            ->reject(fn (AchievementAward $record) => $record->status === AwardStatus::Suppressed)
                            ->each->suppress(auth()->user(), $data['reason']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Suppress, confirm, restore and edit the subtitle, shared by the list rows
     * and the detail page.
     *
     * @return array<int, Action>
     */
    public static function reviewActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (AchievementAward $record): bool => $record->status === AwardStatus::Pending)
                ->modalDescription(fn (AchievementAward $record): string => self::needsCar($record)
                    ? 'Race control does not say which car caused this. Pick the car, or suppress the award instead.'
                    : 'The award becomes final and re-runs will not change it.')
                ->schema(fn (AchievementAward $record): array => self::needsCar($record) ? [
                    Select::make('driver_entry_id')
                        ->label('Car')
                        ->options(fn (): array => DriverEntry::query()
                            ->where('race_session_id', $record->race_session_id)
                            ->with('driver')
                            ->get()
                            ->mapWithKeys(fn (DriverEntry $entry) => [$entry->id => "#{$entry->car_number} {$entry->driver->given_name} {$entry->driver->family_name}"])
                            ->all())
                        ->required(),
                ] : [])
                ->action(fn (AchievementAward $record, array $data) => $record->confirm(
                    auth()->user(),
                    isset($data['driver_entry_id']) ? DriverEntry::find($data['driver_entry_id']) : null,
                )),
            Action::make('suppress')
                ->label('Suppress')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->visible(fn (AchievementAward $record): bool => $record->status !== AwardStatus::Suppressed)
                ->schema([self::reasonField()])
                ->action(fn (AchievementAward $record, array $data) => $record->suppress(auth()->user(), $data['reason'])),
            Action::make('restore')
                ->label('Hand back to the engine')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (AchievementAward $record): bool => $record->isDecided())
                ->requiresConfirmation()
                ->modalDescription('The decision is removed and the next run of oof:achievements judges the award again.')
                ->action(fn (AchievementAward $record) => $record->restore()),
            Action::make('subtitle')
                ->label('Subtitle')
                ->icon('heroicon-o-pencil')
                ->color('gray')
                ->fillForm(fn (AchievementAward $record): array => ['subtitle' => $record->subtitle])
                ->schema([
                    TextInput::make('subtitle')
                        ->helperText('Shown under the name, e.g. the reason for a disqualification ("Scraped the Bottom"). Re-runs keep it.')
                        ->maxLength(255),
                ])
                ->action(fn (AchievementAward $record, array $data) => $record->update(['subtitle' => $data['subtitle'] ?: null])),
        ];
    }

    public static function sessionTitle(AchievementAward $record): string
    {
        $round = $record->raceSession->round;

        return "{$round->season->year} R{$round->round_number} {$round->name} · {$record->raceSession->type->label()}";
    }

    private static function needsCar(AchievementAward $record): bool
    {
        return $record->driver_entry_id === null && $record->achievement->shelf !== AchievementShelf::SafetyCar;
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label('Why suppress it?')
            ->helperText('E.g. a DNS because of illness, or a sensitive weekend. Re-runs never undo a suppression.')
            ->required()
            ->rows(3);
    }
}
