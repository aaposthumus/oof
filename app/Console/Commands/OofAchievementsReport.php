<?php

namespace App\Console\Commands;

use App\Achievements\SessionData;
use App\Achievements\Support\SafetyCarPeriods;
use App\Achievements\Support\TrackLimitDeletions;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Enums\AwardStatus;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\RaceSession;
use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class OofAchievementsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:achievements:report
        {--from=2023 : First season}
        {--to=2026 : Last season}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Report how often each achievement unlocks, with proposed tiers and threshold distributions, as Markdown';

    /**
     * Awards per race weekend from which a tier is proposed: common is Bronze, very rare Platinum.
     */
    private const TIER_FLOORS = [
        'bronze' => 1.0,
        'silver' => 0.25,
        'gold' => 0.05,
    ];

    /**
     * Execute the console command.
     */
    public function handle(MessageClassifier $classifier): int
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');
        $sessions = RaceSession::query()
            ->with('round.season')
            ->whereHas('round.season', fn ($query) => $query->whereBetween('year', [$from, $to]))
            ->whereNotNull('achievements_checked_at')
            ->get();
        $weekends = $sessions->where('type', SessionType::Race)->countBy(fn (RaceSession $session) => $session->round->season->year);
        $seasons = range($from, $to);

        $this->line("# Achievements test run {$from}–{$to}");
        $this->newLine();
        $this->line('Race weekends: '.$weekends->sum().' ('.collect($seasons)->map(fn (int $year) => "{$year}: ".($weekends[$year] ?? 0))->implode(', ').').');
        $this->line('Counts include pending awards and exclude suppressed ones. Proposed tier by awards per weekend: ≥ 1 Bronze, ≥ 0.25 Silver, ≥ 0.05 Gold, below Platinum.');
        $this->newLine();
        $this->frequencies($sessions, $weekends, $seasons);
        $this->distributions($sessions, $classifier);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, RaceSession>  $sessions
     * @param  Collection<int, int>  $weekends
     * @param  list<int>  $seasons
     */
    private function frequencies(Collection $sessions, Collection $weekends, array $seasons): void
    {
        $counts = AchievementAward::query()
            ->whereIn('race_session_id', $sessions->pluck('id'))
            ->where('status', '!=', AwardStatus::Suppressed)
            ->get(['achievement_id', 'race_session_id'])
            ->groupBy('achievement_id')
            ->map(fn (Collection $awards) => $awards->countBy(fn (AchievementAward $award) => $sessions->firstWhere('id', $award->race_session_id)->round->season->year));

        $this->line('| Achievement | Shelf | Tier now | Total | '.implode(' | ', $seasons).' | Per weekend | Proposed tier |');
        $this->line('|---|---|---|---:|'.str_repeat('---:|', count($seasons)).'---:|---|');

        $never = [];

        foreach (Achievement::query()->orderBy('section')->orderBy('id')->get() as $achievement) {
            $bySeason = $counts->get($achievement->id, collect());
            $total = $bySeason->sum();
            $perWeekend = $weekends->sum() === 0 ? 0 : $total / $weekends->sum();

            if ($total === 0) {
                $never[] = $achievement->name;
            }

            $this->line(sprintf(
                '| %s | %s | %s | %d | %s | %.2f | %s |',
                $achievement->name,
                $achievement->shelf->label(),
                $achievement->tier?->label() ?? '–',
                $total,
                collect($seasons)->map(fn (int $year) => $bySeason[$year] ?? 0)->implode(' | '),
                $perWeekend,
                $total === 0 ? '–' : $this->proposedTier($perWeekend)->label(),
            ));
        }

        $this->newLine();
        $this->line('Never triggered: '.($never === [] ? 'none' : implode(', ', $never)).'.');
        $this->newLine();

        $driverless = AchievementAward::query()
            ->whereIn('race_session_id', $sessions->pluck('id'))
            ->whereNull('driver_entry_id')
            ->whereHas('achievement', fn ($query) => $query->where('shelf', '!=', AchievementShelf::SafetyCar))
            ->with('achievement')
            ->get()
            ->countBy(fn (AchievementAward $award) => $award->achievement->name);

        foreach ($driverless as $name => $count) {
            $this->line("- {$name}: {$count} without a clear car, waiting for a person to pick one or suppress.");
        }

        $this->newLine();
    }

    private function proposedTier(float $perWeekend): AchievementTier
    {
        foreach (self::TIER_FLOORS as $tier => $floor) {
            if ($perWeekend >= $floor) {
                return AchievementTier::from($tier);
            }
        }

        return AchievementTier::Platinum;
    }

    /**
     * @param  Collection<int, RaceSession>  $sessions
     */
    private function distributions(Collection $sessions, MessageClassifier $classifier): void
    {
        $blueFlags = collect();
        $deletions = collect();
        $deployments = collect();
        $safetyCarLaps = collect();
        $offences = [];

        foreach ($sessions as $session) {
            $data = SessionData::load($session, $classifier);
            $isRace = in_array($session->type, [SessionType::Race, SessionType::Sprint], true);

            if ($isRace) {
                $blueFlags = $blueFlags->concat($data->messagesOfType(MessageType::BlueFlag)->countBy(fn ($message) => $message->soleEntryId())->except(['']));
                $deletions = $deletions->concat(TrackLimitDeletions::of($data)->countBy('entry_id'));
                $deployments->push($data->messagesOfType(MessageType::SafetyCarDeployed)->count());
                $safetyCarLaps->push(SafetyCarPeriods::of($data)->sum('laps'));
            }

            foreach ($data->threads() as $thread) {
                $offence = $thread->offence?->label() ?? 'Other';
                $offences[$offence]['threads'] = ($offences[$offence]['threads'] ?? 0) + 1;
                $offences[$offence]['penalised'] = ($offences[$offence]['penalised'] ?? 0) + ($thread->penalties() === [] ? 0 : 1);
                $offences[$offence]['noted'] = ($offences[$offence]['noted'] ?? 0) + (collect($thread->messages)->contains(fn ($message) => $message->is(MessageType::IncidentNoted)) && $thread->penalties() === [] ? 1 : 0);
            }
        }

        $this->line('## Distributions (races and sprints)');
        $this->newLine();
        $this->line('| Measure | Samples | p50 | p75 | p90 | p95 | max |');
        $this->line('|---|---:|---:|---:|---:|---:|---:|');
        $this->percentiles('Blue flags per car shown any', $blueFlags->values());
        $this->percentiles('Deleted laps per car with any', $deletions->values());
        $this->percentiles('Safety car deployments per session', $deployments);
        $this->percentiles('Safety car laps per session', $safetyCarLaps);
        $this->newLine();

        foreach ([3, 5, 8, 10, 12] as $threshold) {
            $this->line("- Blue flags ≥ {$threshold}: ".$blueFlags->filter(fn (int $count) => $count >= $threshold)->count().' car-sessions');
        }

        foreach ([5, 8, 10, 15, 20] as $threshold) {
            $this->line("- Safety car laps ≥ {$threshold}: ".$safetyCarLaps->filter(fn (int $laps) => $laps >= $threshold)->count().' sessions');
        }

        $this->newLine();
        $this->line('## Offences (all sessions): incidents, penalised, noted without a penalty');
        $this->newLine();
        $this->line('| Offence | Incidents | Penalised | Noted, no penalty |');
        $this->line('|---|---:|---:|---:|');
        arsort($offences);

        foreach (collect($offences)->sortByDesc('threads') as $offence => $counts) {
            $this->line("| {$offence} | {$counts['threads']} | {$counts['penalised']} | {$counts['noted']} |");
        }
    }

    /**
     * @param  Collection<int, int>  $values
     */
    private function percentiles(string $label, Collection $values): void
    {
        $sorted = $values->sort()->values();
        $at = fn (float $share) => $sorted->isEmpty() ? 0 : $sorted[(int) min($sorted->count() - 1, floor($share * $sorted->count()))];

        $this->line(sprintf('| %s | %d | %d | %d | %d | %d | %d |', $label, $sorted->count(), $at(0.5), $at(0.75), $at(0.9), $at(0.95), $sorted->max() ?? 0));
    }
}
