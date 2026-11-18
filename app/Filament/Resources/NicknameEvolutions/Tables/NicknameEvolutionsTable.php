<?php

namespace App\Filament\Resources\NicknameEvolutions\Tables;

use App\Achievements\Nicknames\OffSeasonOnly;
use App\Enums\EvolutionStatus;
use App\Models\NicknameEvolution;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NicknameEvolutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['driver', 'from', 'to', 'season', 'triggerAward.achievement']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('driver.family_name')
                    ->label('Driver')
                    ->formatStateUsing(fn (NicknameEvolution $record): string => "{$record->driver->given_name} {$record->driver->family_name}"),
                TextColumn::make('to.nickname')
                    ->label('Evolution')
                    ->formatStateUsing(fn (NicknameEvolution $record): string => "{$record->from->nickname} → {$record->to->nickname}"),
                TextColumn::make('reason')
                    ->label('Breakthrough')
                    ->formatStateUsing(fn (NicknameEvolution $record): string => "{$record->reason->label()}, {$record->season->year}")
                    ->description(fn (NicknameEvolution $record): ?string => $record->triggerAward?->title()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EvolutionStatus $state): string => $state->label())
                    ->color(fn (EvolutionStatus $state): string => match ($state) {
                        EvolutionStatus::Proposed => 'warning',
                        EvolutionStatus::Approved => 'info',
                        EvolutionStatus::Applied => 'success',
                        EvolutionStatus::Dismissed => 'gray',
                    }),
                TextColumn::make('effective_on')
                    ->label('Takes effect')
                    ->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(EvolutionStatus::cases())->mapWithKeys(fn (EvolutionStatus $status) => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (NicknameEvolution $record): bool => in_array($record->status, [EvolutionStatus::Proposed, EvolutionStatus::Approved], true))
                    ->fillForm(fn (NicknameEvolution $record): array => ['effective_on' => $record->effective_on])
                    ->schema(fn (NicknameEvolution $record): array => [
                        DatePicker::make('effective_on')
                            ->label('Takes effect on')
                            ->required()
                            ->helperText(($window = $record->offSeason()) === null
                                ? 'The off-season has no dates yet: the next season\'s calendar is not in the database.'
                                : "In the off-season: {$window['from']->toDateString()} to {$window['to']->toDateString()}."),
                    ])
                    ->action(function (NicknameEvolution $record, array $data): void {
                        try {
                            $record->approve(auth()->user(), CarbonImmutable::parse($data['effective_on']));
                        } catch (OffSeasonOnly $exception) {
                            Notification::make()->danger()->title('Not approved')->body($exception->getMessage())->send();

                            throw new Halt;
                        }
                    }),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (NicknameEvolution $record): bool => in_array($record->status, [EvolutionStatus::Proposed, EvolutionStatus::Approved], true))
                    ->requiresConfirmation()
                    ->modalDescription('The planned stage stays planned and is never proposed again for this driver.')
                    ->action(fn (NicknameEvolution $record) => $record->dismiss(auth()->user())),
            ]);
    }
}
