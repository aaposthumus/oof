<?php

namespace App\Filament\Resources\OofscoreStandings\Pages;

use App\Achievements\OofScoreStandings;
use App\Achievements\SeasonNotClosable;
use App\Enums\AchievementShelf;
use App\Filament\Resources\OofscoreStandings\OofscoreStandingResource;
use App\Models\Season;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;

class ListOofscoreStandings extends ListRecords
{
    protected static string $resource = OofscoreStandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuild')
                ->label('Rebuild')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Rebuild the standings of every open season from the final awards. Closed seasons stay frozen.')
                ->action(function (OofScoreStandings $standings): void {
                    $rebuilt = $standings->rebuildSeasons(Season::query()->where('year', '>=', AchievementShelf::Oof->firstSeason())->pluck('id'))->filter();
                    Notification::make()->success()->title('Rebuilt '.($rebuilt->keys()->implode(', ') ?: 'nothing: every season is closed'))->send();
                }),
            Action::make('close')
                ->label('Close season')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->schema([
                    Select::make('season_id')
                        ->label('Season')
                        ->options(fn (): array => Season::query()->where('year', '>=', AchievementShelf::Oof->firstSeason())->whereNull('oofscore_closed_at')->orderByDesc('year')->pluck('year', 'id')->all())
                        ->required(),
                ])
                ->modalDescription('Rebuilds the season a last time, then freezes its OOFscore and its awards\' points for good. Only once its last race is final and no award of it waits for review.')
                ->action(function (array $data, OofScoreStandings $standings): void {
                    $season = Season::query()->findOrFail($data['season_id']);

                    try {
                        $standings->close($season);
                    } catch (SeasonNotClosable $exception) {
                        Notification::make()->danger()->title('Not closed')->body($exception->getMessage())->send();

                        throw new Halt;
                    }

                    Notification::make()->success()->title("{$season->year} is closed")->send();
                }),
        ];
    }
}
