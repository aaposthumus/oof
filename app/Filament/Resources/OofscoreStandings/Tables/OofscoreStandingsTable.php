<?php

namespace App\Filament\Resources\OofscoreStandings\Tables;

use App\Models\OofscoreStanding;
use App\Models\Round;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OofscoreStandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['driver', 'round.season']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('position')->orderBy('driver_id'))
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('position')
                    ->label('#'),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->state(fn (OofscoreStanding $record): string => "{$record->driver->given_name} {$record->driver->family_name}"),
                TextColumn::make('points')
                    ->numeric(),
                TextColumn::make('awards_count')
                    ->label('Awards')
                    ->numeric(),
                TextColumn::make('position_change')
                    ->label('Change')
                    ->formatStateUsing(fn (?int $state): string => match (true) {
                        $state > 0 => "▲ {$state}",
                        $state < 0 => '▼ '.abs($state),
                        default => '–',
                    })
                    ->placeholder('new')
                    ->color(fn (?int $state): string => match (true) {
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('round_id')
                    ->label('After round')
                    ->options(fn (): array => self::rounds())
                    ->default(fn (): ?int => array_key_first(self::rounds()))
                    ->selectablePlaceholder(false),
            ]);
    }

    /**
     * Rounds with standings, latest first: "2026 R19 Mexico City Grand Prix".
     *
     * @return array<int, string>
     */
    public static function rounds(): array
    {
        return Round::query()
            ->whereIn('id', OofscoreStanding::query()->select('round_id'))
            ->with('season')
            ->get()
            ->sortByDesc(fn (Round $round) => sprintf('%04d-%03d', $round->season->year, $round->round_number))
            ->mapWithKeys(fn (Round $round) => [$round->id => "{$round->season->year} R{$round->round_number} {$round->name}".($round->season->isClosed() ? ' (closed)' : '')])
            ->all();
    }
}
