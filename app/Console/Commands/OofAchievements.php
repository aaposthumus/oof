<?php

namespace App\Console\Commands;

use App\Achievements\AchievementEngine;
use App\Achievements\Nicknames\Breakthroughs;
use App\Achievements\OofScoreStandings;
use App\Achievements\RunLog;
use App\Achievements\SessionData;
use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\RaceSession;
use App\Models\Season;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OofAchievements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:achievements
        {--season=* : Only these seasons (default: all from achievements.first_season)}
        {--round= : Only this round number}
        {--session= : Only this session type, e.g. race, qualifying, practice_1 (weekends and seasons still run)}
        {--scope=* : Only these scopes: session, weekend, season (default: all three)}
        {--changed : Only rounds with new data since their last run, or pending awards that are now final; and seasons whose awards changed since}
        {--stale : Only what was decided on an older catalogue version, or never logged; and sessions whose surprise window changed since}
        {--dry-run : Show what would be awarded without storing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock achievements per session, weekend and season (safe to re-run; updates awards and keeps manual decisions), rebuild the OOFscore of the seasons touched, propose nickname evolutions after breakthroughs, and log each run';

    /**
     * Tables whose rows feed the detectors, by their link to a session.
     */
    private const SOURCES = [
        'race_control_messages' => 'race_session_id',
        'session_laps' => 'race_session_id',
        'pit_lane_visits' => 'race_session_id',
        'positions' => 'race_session_id',
        'driver_entries' => 'race_session_id',
        'results' => 'driver_entry_id',
        'qualifying_results' => 'driver_entry_id',
    ];

    /**
     * Runs of a dry run, written to the log after it is rolled back.
     *
     * @var list<array{0: AwardScope, 1: string, 2: int|null, 3: array<string, array<string, int>>, 4: CarbonImmutable}>
     */
    private array $dryRuns = [];

    /**
     * Execute the console command.
     */
    public function handle(AchievementEngine $engine, OofScoreStandings $standings, RunLog $log, Breakthroughs $breakthroughs): int
    {
        $sessionType = $this->option('session') === null ? null : SessionType::tryFrom($this->option('session'));

        if ($this->option('session') !== null && $sessionType === null) {
            $this->components->error('Unknown session type ['.$this->option('session').']. Available: '.implode(', ', array_column(SessionType::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $scopes = $this->scopes();

        if ($scopes === null) {
            $this->components->error('Unknown scope. Available: '.implode(', ', array_column(AwardScope::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry run: nothing is stored.');
        }

        [$sessions, $seasonIds] = $this->select($sessionType, $log);
        $totals = [];
        $counts = ['session' => 0, 'weekend' => 0, 'season' => 0];
        $add = function (array $stats) use (&$totals): void {
            foreach ($stats as $key => $stat) {
                foreach ($stat as $label => $count) {
                    $totals[$key][$label] = ($totals[$key][$label] ?? 0) + $count;
                }
            }
        };

        // One transaction around a dry run, so the weekend still sees what the sessions unlocked.
        if ($dryRun) {
            DB::beginTransaction();
        }

        foreach ($sessions->groupBy('round_id') as $roundSessions) {
            $round = $roundSessions->first()->round;

            if (in_array(AwardScope::Session, $scopes, true)) {
                foreach ($roundSessions->when($sessionType !== null, fn (Collection $all) => $all->where('type', $sessionType)) as $session) {
                    $startedAt = CarbonImmutable::now();
                    $stats = $engine->run($session);
                    $add($stats);
                    $counts['session']++;
                    $this->log($log, AwardScope::Session, "session:{$session->id}", $round->season_id, $stats, $startedAt, $dryRun);
                }
            }

            if (in_array(AwardScope::Weekend, $scopes, true)) {
                $startedAt = CarbonImmutable::now();
                $stats = $engine->runWeekend($round);
                $add($stats);
                $counts['weekend']++;
                $this->log($log, AwardScope::Weekend, "round:{$round->id}", $round->season_id, $stats, $startedAt, $dryRun);
            }
        }

        // The OOFscore of every open season the run touched follows its awards; the Oofies read it.
        $rebuilt = $standings->rebuildSeasons($sessions->map(fn (RaceSession $session) => $session->round->season_id)->unique())->filter();

        if (in_array(AwardScope::Season, $scopes, true)) {
            foreach (Season::query()->whereIn('id', $seasonIds)->orderBy('year')->get() as $season) {
                $startedAt = CarbonImmutable::now();
                $stats = $engine->runSeason($season);

                // A season that is not final yet is not decided, so there is nothing to log.
                if ($stats !== null) {
                    $add($stats);
                    $counts['season']++;
                    $this->log($log, AwardScope::Season, "season:{$season->id}", $season->id, $stats, $startedAt, $dryRun);
                }
            }
        }

        // Breakthroughs read the final awards just stored: nickname evolutions are proposed for the seasons touched.
        $proposed = $dryRun ? collect() : Season::query()
            ->whereIn('id', $sessions->toBase()->map(fn (RaceSession $session) => $session->round->season_id)->concat(collect($seasonIds)->all())->unique()->values())
            ->orderBy('year')
            ->get()
            ->flatMap(fn (Season $season) => $breakthroughs->check($season));

        if ($dryRun) {
            DB::rollBack();

            foreach ($this->dryRuns as [$scope, $contextKey, $seasonId, $stats, $startedAt]) {
                $log->record($scope, $contextKey, $seasonId, $this->runOptions(), $stats, true, $startedAt);
            }
        }

        ksort($totals);
        $this->components->info("{$counts['session']} sessions, {$counts['weekend']} weekends, {$counts['season']} seasons");

        if ($rebuilt->isNotEmpty()) {
            $this->components->info('OOFscore rebuilt for '.$rebuilt->keys()->implode(', ').'.');
        }

        if ($proposed->isNotEmpty()) {
            $this->components->info("{$proposed->count()} nickname evolutions proposed.");
        }

        if ($totals !== []) {
            $this->table(
                ['Achievement', 'Found', 'Created', 'Updated', 'Removed', 'Pending'],
                collect($totals)->map(fn (array $stats, string $key) => [$key, ...array_values($stats)])->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return list<AwardScope>|null Null when one is unknown
     */
    private function scopes(): ?array
    {
        $scopes = array_map(fn (string $scope) => AwardScope::tryFrom($scope), $this->option('scope'));

        if (in_array(null, $scopes, true)) {
            return null;
        }

        return $scopes === [] ? AwardScope::cases() : $scopes;
    }

    /**
     * @param  array<string, array<string, int>>  $stats
     */
    private function log(RunLog $log, AwardScope $scope, string $contextKey, ?int $seasonId, array $stats, CarbonImmutable $startedAt, bool $dryRun): void
    {
        if ($dryRun) {
            $this->dryRuns[] = [$scope, $contextKey, $seasonId, $stats, $startedAt];

            return;
        }

        $log->record($scope, $contextKey, $seasonId, $this->runOptions(), $stats, false, $startedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function runOptions(): array
    {
        return array_filter(array_intersect_key($this->options(), array_flip(['season', 'round', 'session', 'scope', 'changed', 'stale', 'dry-run'])), fn ($value) => $value !== null && $value !== false && $value !== []);
    }

    /**
     * The sessions to run, in the order they were held (grouped per round,
     * whose weekend runs after them), and the seasons to decide.
     *
     * @return array{0: Collection<int, RaceSession>, 1: Collection<int, int>}
     */
    private function select(?SessionType $type, RunLog $log): array
    {
        // A session runs once it has ended, so practice and qualifying are decided on a live
        // weekend before the race; their awards wait for their own final-after hours.
        $sessions = RaceSession::query()
            ->with(['round.season', 'openF1Session'])
            ->where(fn (Builder $query) => $query
                ->where('scheduled_date', '<=', now()->toDateString())
                ->orWhere(fn (Builder $query) => $query->whereNull('scheduled_date')->whereHas('round', fn (Builder $round) => $round->where('date', '<=', now()->toDateString()))))
            ->whereHas('round', fn (Builder $round) => $round
                ->when($this->option('round'), fn (Builder $query, $number) => $query->where('round_number', (int) $number))
                ->whereHas('season', fn (Builder $season) => $season
                    ->where('year', '>=', config('achievements.first_season'))
                    ->when($this->option('season'), fn (Builder $query, array $years) => $query->whereIn('year', array_map('intval', $years)))))
            ->get()
            ->filter(fn (RaceSession $session) => $session->endsAt()->lessThanOrEqualTo(CarbonImmutable::now()))
            ->values();

        // A session filter narrows the session scope only, so the round must keep a session to hang its weekend on.
        if ($type !== null) {
            $sessions = $sessions->whereIn('round_id', $sessions->where('type', $type)->pluck('round_id'));
        }

        $allSeasons = $sessions->pluck('round.season_id')->unique()->values();

        if ($this->option('changed') || $this->option('stale')) {
            $rounds = collect()
                ->concat($this->option('changed') ? $sessions->filter(fn (RaceSession $session) => $this->hasChanged($session))->pluck('round_id') : [])
                ->concat($this->option('stale') ? $this->staleRounds($sessions, $log) : [])
                ->unique();
            $seasons = collect()
                ->concat($this->option('changed') ? $this->changedSeasons($allSeasons, $log) : [])
                ->concat($this->option('stale') ? $allSeasons->filter(fn (int $seasonId) => $log->isStale($log->latest(["season:{$seasonId}"])->first())) : []);
            $sessions = $sessions->whereIn('round_id', $rounds);
            $allSeasons = $seasons->concat($sessions->pluck('round.season_id'))->unique()->values();
        }

        $sessions = $sessions
            ->sortBy(fn (RaceSession $session) => sprintf('%04d-%03d-%d', $session->round->season->year, $session->round->round_number, $session->type->weekendOrder()))
            ->values();

        return [$sessions, $allSeasons];
    }

    /**
     * Rounds with a session or weekend decided on an older catalogue
     * version (or never logged), or a session whose surprise window changed.
     * Only what was run before counts; what never ran is for --changed.
     *
     * @param  Collection<int, RaceSession>  $sessions
     * @return Collection<int, int>
     */
    private function staleRounds(Collection $sessions, RunLog $log): Collection
    {
        $run = $sessions->filter(fn (RaceSession $session) => $session->achievements_checked_at !== null);
        $latest = $log->latest([
            ...$run->map(fn (RaceSession $session) => "session:{$session->id}"),
            ...$run->map(fn (RaceSession $session) => "round:{$session->round_id}"),
        ]);

        return $run
            ->filter(function (RaceSession $session) use ($latest, $log) {
                $sessionRun = $latest->get("session:{$session->id}");

                return $log->isStale($sessionRun)
                    || $log->isStale($latest->get("round:{$session->round_id}"))
                    || $log->earlierRacesChanged($session, $sessionRun);
            })
            ->pluck('round_id')
            ->unique()
            ->values();
    }

    /**
     * Seasons whose session and weekend awards changed since the season was
     * last decided, or that were never decided: a decision in the CMS can
     * change the Oofies. The engine decides them only once they are final.
     *
     * @param  Collection<int, int>  $seasonIds
     * @return Collection<int, int>
     */
    private function changedSeasons(Collection $seasonIds, RunLog $log): Collection
    {
        $latest = $log->latest($seasonIds->map(fn (int $id) => "season:{$id}"));

        return $seasonIds->filter(function (int $seasonId) use ($latest) {
            $run = $latest->get("season:{$seasonId}");

            return $run === null || AchievementAward::query()
                ->where('season_id', $seasonId)
                ->where('scope', '!=', AwardScope::Season)
                ->where('updated_at', '>', $run->finished_at)
                ->exists();
        })->values();
    }

    /**
     * New data since the session's last run, or pending awards whose session is final by now.
     */
    private function hasChanged(RaceSession $session): bool
    {
        $checkedAt = $session->achievements_checked_at;

        if ($checkedAt === null) {
            return true;
        }

        foreach (self::SOURCES as $table => $link) {
            $rows = DB::table($table)->where('updated_at', '>', $checkedAt);
            $rows = $link === 'race_session_id'
                ? $rows->where('race_session_id', $session->id)
                : $rows->whereIn('driver_entry_id', DB::table('driver_entries')->where('race_session_id', $session->id)->select('id'));

            if ($rows->exists()) {
                return true;
            }
        }

        // Pending awards anchored to the session: its own, and the weekend's decided after it.
        if (! AchievementAward::query()->where('race_session_id', $session->id)->where('status', AwardStatus::Pending)->whereNull('decided_at')->exists()) {
            return false;
        }

        $finalAt = SessionData::load($session, app(MessageClassifier::class))->finalAt();

        return $checkedAt->lessThan($finalAt) && $finalAt->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
