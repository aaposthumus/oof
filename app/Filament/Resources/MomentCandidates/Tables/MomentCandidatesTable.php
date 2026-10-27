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
                SelectFilter::make('status')
                    ->options(collect(MomentCandidateStatus::cases())->mapWithKeys(fn (MomentCandidateStatus $status) => [$status->value => ucfirst($status->value)])->all())
                    ->default(MomentCandidateStatus::New->value),
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
                        ->action(fn (Collection $records) => $records->each->accept())
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => MomentCandidateStatus::Rejected]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('block')
                        ->label('Block')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => MomentCandidateStatus::Blocked]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Accept, reject and block, shared by the list rows and the detail page.
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
                ->action(function (MomentCandidate $record, $livewire): void {
                    $moment = $record->accept();

                    $livewire->redirect(HistoricMomentResource::getUrl('edit', ['record' => $moment]));
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(fn (MomentCandidate $record) => $record->update(['status' => MomentCandidateStatus::Rejected])),
            Action::make('block')
                ->label('Block')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn (MomentCandidate $record) => $record->update(['status' => MomentCandidateStatus::Blocked])),
        ];
    }
}
