<?php

namespace App\Filament\Resources\MomentCandidates\Tables;

use App\Enums\MomentCandidateStatus;
use App\Excavation\Support\EvidenceSummary;
use App\Filament\Resources\HistoricMoments\HistoricMomentResource;
use App\Models\MomentCandidate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MomentCandidatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['round.season', 'driver', 'excavationRun']))
            ->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('score')
                    ->numeric(1)
                    ->sortable(),
                TextColumn::make('type')
                    ->formatStateUsing(fn (string $state): string => EvidenceSummary::typeLabel($state))
                    ->sortable(),
                TextColumn::make('round_title')
                    ->label('Year and round')
                    ->state(fn (MomentCandidate $record): string => "{$record->round->season->year} {$record->round->name}"),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->state(fn (MomentCandidate $record): ?string => $record->driver ? "{$record->driver->given_name} {$record->driver->family_name}" : null),
                TextColumn::make('evidence_summary')
                    ->label('Evidence')
                    ->state(fn (MomentCandidate $record): string => EvidenceSummary::summary($record))
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MomentCandidateStatus $state): string => ucfirst($state->value))
                    ->color(fn (MomentCandidateStatus $state): string => match ($state) {
                        MomentCandidateStatus::New => 'info',
                        MomentCandidateStatus::Accepted => 'success',
                        MomentCandidateStatus::Rejected => 'gray',
                        MomentCandidateStatus::Blocked => 'danger',
                    }),
                TextColumn::make('sensitive_badge')
                    ->label('')
                    ->state(fn (MomentCandidate $record): ?string => $record->is_sensitive ? 'Sensitive' : null)
                    ->badge()
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(EvidenceSummary::typeLabels()),
                TernaryFilter::make('is_sensitive')
                    ->label('Sensitive')
                    ->trueLabel('Sensitive only')
                    ->falseLabel('Not sensitive'),
                Filter::make('season_range')
                    ->schema([
                        TextInput::make('from_year')->label('From season')->numeric()->minValue(1950),
                        TextInput::make('to_year')->label('To season')->numeric()->minValue(1950),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query->whereHas('round.season', function (Builder $season) use ($data): void {
                        $season
                            ->when($data['from_year'] ?? null, fn (Builder $q, $year) => $q->where('year', '>=', $year))
                            ->when($data['to_year'] ?? null, fn (Builder $q, $year) => $q->where('year', '<=', $year));
                    })),
                Filter::make('minimum_score')
                    ->schema([
                        TextInput::make('minimum_score')->numeric()->minValue(0)->maxValue(100),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['minimum_score'] ?? null,
                        fn (Builder $q, $score) => $q->where('score', '>=', $score),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                ...self::reviewActions(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('accept')
                        ->label('Accept')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->hidden(fn ($livewire): bool => self::filteredOn($livewire, MomentCandidateStatus::Accepted))
                        ->action(fn (Collection $records) => $records->reject(fn (MomentCandidate $record) => $record->status === MomentCandidateStatus::Accepted)->each->accept())
                        ->deselectRecordsAfterCompletion(),
                    ...collect([
                        [MomentCandidateStatus::Rejected, 'reject', 'Reject', 'heroicon-o-x-mark', 'gray'],
                        [MomentCandidateStatus::Blocked, 'block', 'Block', 'heroicon-o-no-symbol', 'danger'],
                        [MomentCandidateStatus::New, 'reset', 'Reset to new', 'heroicon-o-arrow-uturn-left', 'info'],
                    ])->map(fn (array $definition) => BulkAction::make($definition[1])
                        ->label($definition[2])
                        ->icon($definition[3])
                        ->color($definition[4])
                        ->requiresConfirmation()
                        ->modalDescription('Accepted candidates lose their draft historic moment when it is untouched (draft, empty story). A moment that was edited or published remains.')
                        ->hidden(fn ($livewire): bool => self::filteredOn($livewire, $definition[0]))
                        ->action(fn (Collection $records) => $records->reject(fn (MomentCandidate $record) => $record->status === $definition[0])->each->changeStatus($definition[0]))
                        ->deselectRecordsAfterCompletion())->all(),
                ]),
            ]);
    }

    /**
     * Whether the list shows one status tab, so the bulk action that sets it changes nothing.
     */
    private static function filteredOn(object $livewire, MomentCandidateStatus $status): bool
    {
        return $livewire->activeStatus() === $status;
    }

    /**
     * Accept, reject, block and reset to new, shared by the list rows and the
     * detail page. Each shows only while it would change the status. Leaving
     * accepted asks for confirmation when the historic moment stays.
     *
     * @return array<int, Action>
     */
    public static function reviewActions(): array
    {
        return [
            Action::make('accept')
                ->label('Accept')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (MomentCandidate $record): bool => $record->status !== MomentCandidateStatus::Accepted)
                ->action(function (MomentCandidate $record, $livewire): void {
                    $moment = $record->accept();

                    $livewire->redirect(HistoricMomentResource::getUrl('edit', ['record' => $moment]));
                }),
            self::statusAction('reject', 'Reject', 'heroicon-o-x-mark', 'gray', MomentCandidateStatus::Rejected),
            self::statusAction('block', 'Block', 'heroicon-o-no-symbol', 'danger', MomentCandidateStatus::Blocked, alwaysConfirm: true),
            self::statusAction('reset', 'Reset to new', 'heroicon-o-arrow-uturn-left', 'info', MomentCandidateStatus::New),
        ];
    }

    private static function statusAction(string $name, string $label, string $icon, string $color, MomentCandidateStatus $status, bool $alwaysConfirm = false): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (MomentCandidate $record): bool => $record->status !== $status)
            ->requiresConfirmation(fn (MomentCandidate $record): bool => $alwaysConfirm || $record->keepsMomentWhenLeavingAccepted())
            ->modalDescription(fn (MomentCandidate $record): ?string => $record->keepsMomentWhenLeavingAccepted()
                ? 'The historic moment of this candidate has been edited or published, so it will remain after this change. Delete it from the historic moments if you no longer want it.'
                : null)
            ->action(fn (MomentCandidate $record) => $record->changeStatus($status));
    }
}
