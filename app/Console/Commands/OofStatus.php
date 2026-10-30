<?php

namespace App\Console\Commands;

use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\ConstructorStanding;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\DriverStanding;
use App\Models\LapTime;
use App\Models\PitLaneVisit;
use App\Models\PitStop;
use App\Models\Position;
use App\Models\QualifyingResult;
use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use App\Models\RawResponse;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonDriver;
use App\Models\SeasonTeam;
use App\Models\SessionLap;
use App\Models\Stint;
use App\Models\Team;
use App\Models\TeamRadioMessage;
use App\Models\WeatherSample;
use App\OpenF1\MatchReport;
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
    public function handle(MatchReport $openF1): int
    {
        $this->showRawResponses();
        $this->showTableCounts();
        $this->showLatestRaceRound();
        $this->showOpenF1Matching($openF1);

        return self::SUCCESS;
    }

    protected function showRawResponses(): void
    {
        $rawOnly = fn (Builder $query) => $query->where('source', 'openf1')->whereIn('endpoint', config('openf1.raw_only_endpoints'));
        $pending = RawResponse::query()
            ->pendingNormalization()
            ->whereNot($rawOnly)
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($total) => (int) $total);
        $total = RawResponse::count();
        $rawOnlyTotal = RawResponse::query()->where($rawOnly)->count();
        $latestFetch = RawResponse::max('fetched_at');

        $this->components->info('raw_responses');
        $this->components->twoColumnDetail('Total', number_format($total));
        $this->components->twoColumnDetail('Normalized', number_format($total - $rawOnlyTotal - $pending->sum()));
        $this->components->twoColumnDetail('Pending', number_format($pending->sum()).$this->bySource($pending->all()));
        $this->components->twoColumnDetail('Stored raw only', number_format($rawOnlyTotal).' (OpenF1 '.implode(', ', config('openf1.raw_only_endpoints')).')');
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
            'race_control_messages' => RaceControlMessage::class,
            'session_laps' => SessionLap::class,
            'stints' => Stint::class,
            'pit_lane_visits' => PitLaneVisit::class,
            'positions' => Position::class,
            'weather_samples' => WeatherSample::class,
            'team_radio_messages' => TeamRadioMessage::class,
            'achievement_awards' => AchievementAward::class,
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

    protected function showOpenF1Matching(MatchReport $report): void
    {
        $this->newLine();
        $this->components->info('OpenF1 matching');

        foreach ($report->counts() as $ledger => $counts) {
            $this->components->twoColumnDetail(ucfirst($ledger), $counts === [] ? 'none' : collect($counts)->map(fn (int $count, string $status) => "{$count} {$status}")->implode(', '));
        }

        $this->components->twoColumnDetail('Raced rounds without a meeting', (string) $report->roundsWithoutMeeting());

        $attention = $report->needingAttention();

        if ($attention !== []) {
            $this->newLine();
            $this->components->warn('Unmatched or conflicting (newest first, at most 20)');
            $this->table(['Kind', 'Status', 'What', 'Note'], $attention);
        }
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function bySource(array $counts): string
    {
        return $counts === [] ? '' : ' ('.collect($counts)->map(fn (int $count, string $source) => "{$source} {$count}")->implode(', ').')';
    }
}
