<?php

namespace App\Console\Commands;

use App\Achievements\AchievementEngine;
use App\Achievements\SessionData;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\RaceSession;
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
        {--session= : Only this session type, e.g. race, qualifying, practice_1}
        {--changed : Only rounds with new data since their last run, or pending awards that are now final}
        {--dry-run : Show what would be awarded without storing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock achievements per session (safe to re-run; updates awards and keeps manual decisions)';

    /**
     * Sessions in the order they run, so the race comes last and can look at the whole weekend.
     */
    private const ORDER = [
        SessionType::Practice1, SessionType::Practice2, SessionType::Practice3,
        SessionType::SprintQualifying, SessionType::Sprint, SessionType::Qualifying, SessionType::Race,
    ];

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
     * Execute the console command.
     */
    public function handle(AchievementEngine $engine): int
    {
        $sessionType = $this->option('session') === null ? null : SessionType::tryFrom($this->option('session'));

        if ($this->option('session') !== null && $sessionType === null) {
            $this->components->error('Unknown session type ['.$this->option('session').']. Available: '.implode(', ', array_column(SessionType::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry run: nothing is stored.');
        }

        $sessions = $this->sessions($sessionType);
        $totals = [];

        // One transaction around a dry run, so the race still sees what the weekend's other sessions unlocked.
        if ($dryRun) {
            DB::beginTransaction();
        }

        foreach ($sessions as $session) {
            foreach ($engine->run($session) as $key => $stats) {
                foreach ($stats as $label => $count) {
                    $totals[$key][$label] = ($totals[$key][$label] ?? 0) + $count;
                }
            }
        }

        if ($dryRun) {
            DB::rollBack();
        }

        ksort($totals);
        $this->components->info("{$sessions->count()} sessions");

        if ($totals !== []) {
            $this->table(
                ['Achievement', 'Found', 'Created', 'Updated', 'Removed', 'Pending'],
                collect($totals)->map(fn (array $stats, string $key) => [$key, ...array_values($stats)])->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, RaceSession>
     */
    private function sessions(?SessionType $type): Collection
    {
        $sessions = RaceSession::query()
            ->with('round.season')
            ->whereHas('round', fn (Builder $round) => $round
                ->where('date', '<=', now()->toDateString())
                ->when($this->option('round'), fn (Builder $query, $number) => $query->where('round_number', (int) $number))
                ->whereHas('season', fn (Builder $season) => $season
                    ->where('year', '>=', config('achievements.first_season'))
                    ->when($this->option('season'), fn (Builder $query, array $years) => $query->whereIn('year', array_map('intval', $years)))))
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->get();

        if ($this->option('changed')) {
            $rounds = $sessions->filter(fn (RaceSession $session) => $this->hasChanged($session))->pluck('round_id')->unique();
            $sessions = $sessions->whereIn('round_id', $rounds);
        }

        return $sessions
            ->sortBy(fn (RaceSession $session) => sprintf('%04d-%03d-%d', $session->round->season->year, $session->round->round_number, array_search($session->type, self::ORDER, true)))
            ->values();
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

        if (! $session->achievementAwards()->where('status', AwardStatus::Pending)->whereNull('decided_at')->exists()) {
            return false;
        }

        $finalAt = SessionData::load($session, app(MessageClassifier::class))->endsAt()->addHours((int) config('achievements.final_after_hours'));

        return $checkedAt->lessThan($finalAt) && $finalAt->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
