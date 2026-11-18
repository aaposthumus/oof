<?php

namespace App\Achievements;

use App\Achievements\Facts\Requirements;
use App\Achievements\Support\TeamForm;
use App\Enums\AwardScope;
use App\Models\Achievement;
use App\Models\AchievementRun;
use App\Models\RaceSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The run log (achievement_runs): which contexts the engine decided, when,
 * and on which catalogue version. A context is stale when its last real run
 * (not a dry run) was on another version, or it has no run in the log; a
 * session whose achievements read earlier races (the surprise rule's team
 * form) is also stale when a result in its window changed after that run.
 */
class RunLog
{
    private ?string $version = null;

    /**
     * @var array<string, bool> By session type
     */
    private array $readsEarlierRaces = [];

    public function __construct(private Catalogue $catalogue, private Requirements $requirements) {}

    public function version(): string
    {
        return $this->version ??= $this->catalogue->version();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, array<string, int>>  $stats
     */
    public function record(AwardScope $scope, string $contextKey, ?int $seasonId, array $options, array $stats, bool $isDryRun, CarbonImmutable $startedAt): AchievementRun
    {
        return AchievementRun::create([
            'scope' => $scope,
            'context_key' => $contextKey,
            'season_id' => $seasonId,
            'catalogue_version' => $this->version(),
            'options' => $options,
            'stats' => $stats,
            'is_dry_run' => $isDryRun,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    /**
     * The last real run of each context.
     *
     * @param  iterable<string>  $contextKeys
     * @return Collection<string, AchievementRun> Keyed by context key
     */
    public function latest(iterable $contextKeys): Collection
    {
        $keys = collect($contextKeys)->unique()->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        $latestIds = AchievementRun::query()
            ->whereIn('context_key', $keys)
            ->where('is_dry_run', false)
            ->groupBy('context_key')
            ->select(DB::raw('max(id) as id'));

        return AchievementRun::query()->whereIn('id', $latestIds)->get()->keyBy('context_key');
    }

    public function isStale(?AchievementRun $run): bool
    {
        return $run === null || $run->catalogue_version !== $this->version();
    }

    /**
     * Whether a result in the team-form window before the session's round
     * changed after its last run, for a session where an achievement that
     * reads earlier races is looked for.
     */
    public function earlierRacesChanged(RaceSession $session, AchievementRun $run): bool
    {
        if (! $this->readsEarlierRaces($session)) {
            return false;
        }

        $window = TeamForm::before($session->round)->races->pluck('id');

        return DB::table('results')
            ->join('driver_entries', 'driver_entries.id', '=', 'results.driver_entry_id')
            ->join('season_teams', 'season_teams.id', '=', 'driver_entries.season_team_id')
            ->whereIn('driver_entries.race_session_id', $window)
            ->where(fn ($query) => $query
                ->where('results.updated_at', '>', $run->finished_at)
                ->orWhere('driver_entries.updated_at', '>', $run->finished_at)
                ->orWhere('season_teams.updated_at', '>', $run->finished_at))
            ->exists();
    }

    private function readsEarlierRaces(RaceSession $session): bool
    {
        return $this->readsEarlierRaces[$session->type->value] ??= Achievement::query()->get()->contains(fn (Achievement $achievement) => $this->catalogue->has($achievement->key)
            && $achievement->appliesTo($session->type)
            && $this->requirements->for($achievement->key)['reads_earlier_races']);
    }
}
