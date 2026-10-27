<?php

namespace App\Filament\Widgets;

use App\Enums\MomentCandidateStatus;
use App\Enums\SessionType;
use App\Models\MomentCandidate;
use App\Models\Round;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $toReview = MomentCandidate::where('status', MomentCandidateStatus::New);

        $latestRound = Round::query()
            ->with('season')
            ->whereHas('raceSessions', fn (Builder $sessions) => $sessions
                ->where('type', SessionType::Race)
                ->whereHas('driverEntries.result'))
            ->orderByDesc('date')
            ->first();

        return [
            Stat::make('Candidates to review', (clone $toReview)->count()),
            Stat::make('Sensitive candidates to review', (clone $toReview)->where('is_sensitive', true)->count())
                ->color('danger'),
            Stat::make('Accepted moments', MomentCandidate::where('status', MomentCandidateStatus::Accepted)->count()),
            Stat::make('Latest round with race results', $latestRound ? "{$latestRound->season->year} {$latestRound->name}" : 'None')
                ->description($latestRound?->date->toFormattedDateString()),
        ];
    }
}
