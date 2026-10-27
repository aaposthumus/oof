<?php

namespace App\Console\Commands;

use App\Enums\SessionType;
use App\Models\ConstructorStanding;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\LapTime;
use App\Models\PitStop;
use App\Models\QualifyingResult;
use App\Models\RaceSession;
use App\Models\RawResponse;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Models\SeasonTeam;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class OofStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show a compact overview of the raw layer and the core tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showRawResponses();
        $this->showTableCounts();
        $this->showLatestRaceRound();

        return self::SUCCESS;
    }

    protected function showRawResponses(): void
    {
        $pending = RawResponse::query()
            ->where(fn (Builder $query) => $query
                ->whereNull('normalized_at')
                ->orWhereColumn('normalized_at', '<', 'fetched_at'))
            ->count();
        $total = RawResponse::count();
        $latestFetch = RawResponse::max('fetched_at');

        $this->components->info('raw_responses');
        $this->components->twoColumnDetail('Total', number_format($total));
        $this->components->twoColumnDetail('Normalized', number_format($total - $pending));
        $this->components->twoColumnDetail('Pending', number_format($pending));
        $this->components->twoColumnDetail('Last fetched', $latestFetch ?? 'never');
    }

    protected function showTableCounts(): void
    {
        $seasonRange = Season::query()->selectRaw('min(year) as first_year, max(year) as last_year')->first();

        $rows = [
            ['seasons', number_format(Season::count()).($seasonRange->first_year ? " ({$seasonRange->first_year}–{$seasonRange->last_year})" : '')],
        ];

        $models = [
            'rounds' => Round::class,
            'race_sessions' => RaceSession::class,
            'drivers' => Driver::class,
            'teams' => Team::class,
            'season_teams' => SeasonTeam::class,
            'season_drivers' => SeasonDriver::class,
            'driver_entries' => DriverEntry::class,
            'results' => Result::class,
            'qualifying_results' => QualifyingResult::class,
            'driver_standings' => DriverStanding::class,
            'constructor_standings' => ConstructorStanding::class,
            'pit_stops' => PitStop::class,
            'lap_times' => LapTime::class,
        ];

        foreach ($models as $table => $model) {
            $rows[] = [$table, number_format($model::count())];
        }

        $this->newLine();
        $this->components->info('Core tables');
        $this->table(['Table', 'Rows'], $rows);
    }

    protected function showLatestRaceRound(): void
    {
        $round = Round::query()
            ->with('season')
            ->whereHas('raceSessions', fn (Builder $sessions) => $sessions
                ->where('type', SessionType::Race)
                ->whereHas('driverEntries.result'))
            ->orderByDesc('date')
            ->first();

        $this->components->info('Latest round with race results');

        if ($round === null) {
            $this->components->twoColumnDetail('Round', 'none');

            return;
        }

        $this->components->twoColumnDetail('Round', "{$round->season->year} round {$round->round_number} – {$round->name}");
        $this->components->twoColumnDetail('Date', $round->date->toDateString());
    }
}
