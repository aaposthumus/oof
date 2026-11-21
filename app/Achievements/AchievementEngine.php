<?php

namespace App\Achievements;

use App\Achievements\Facts\Requirements;
use App\Achievements\Rules\Cascade;
use App\Achievements\Rules\CrossCheck;
use App\Achievements\Rules\Fairness;
use App\Achievements\Rules\Finality;
use App\Achievements\Rules\Sensitivity;
use App\Achievements\Rules\Stacking;
use App\Achievements\Triggers\SeasonTrigger;
use App\Achievements\Triggers\Trigger;
use App\Achievements\Triggers\TriggerFactory;
use App\Enums\AwardReviewReason;
use App\Enums\AwardScope;
use App\Enums\AwardStatus;
use App\Enums\RecipientType;
use App\Enums\SessionType;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\AchievementVariant;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the catalogue's triggers, applies the rules and stores the awards,
 * per context: a session, a weekend (round) or a season. Re-running a
 * context updates its awards in place, never duplicates them, and never
 * changes what a person decided.
 *
 * Per round the sessions run first, in weekend order, then the weekend:
 * its achievements are decided after their deciding session has ended,
 * from the sessions they list, and stored against the round with the
 * deciding session as anchor. A season is decided once its final race is
 * final. An achievement is only looked for where its data supports it
 * (see Requirements).
 *
 * Order in a session: propose sensitivities, detect, fairness, cross-check,
 * cascade, sensitivity, stacking, the late triggers, sensitivity again, then
 * finality. A weekend achievement that supersedes a session achievement
 * (Fine Dining over Table for One) joins the session's cascade, so the
 * session never stores what the weekend replaces.
 *
 * @phpstan-type Stats array<string, array{found: int, created: int, updated: int, removed: int, pending: int}>
 */
class AchievementEngine
{
    public function __construct(
        private TriggerFactory $triggers,
        private Requirements $requirements,
        private MessageClassifier $classifier,
        private Fairness $fairness,
        private CrossCheck $crossCheck,
        private Cascade $cascade,
        private Stacking $stacking,
        private Finality $finality,
        private Sensitivity $sensitivity,
    ) {}

    /**
     * @return array<string, Trigger|SeasonTrigger> Keyed by achievement key, in run order
     */
    public function triggers(): array
    {
        return $this->triggers->all();
    }

    public function trigger(string $key): Trigger|SeasonTrigger
    {
        return $this->triggers->for($key);
    }

    /**
     * The settled detections of a session, before they are stored.
     *
     * @return Collection<int, Detection>
     */
    public function detect(SessionData $data): Collection
    {
        $catalogue = $this->catalogue();
        $triggers = collect($this->triggers())
            ->filter(fn (Trigger|SeasonTrigger $trigger, string $key) => $catalogue->has($key) && $catalogue[$key]->appliesTo($data->type()) && $this->requirements->allows($key, $data));

        $detections = $triggers
            ->reject(fn (Trigger $trigger) => $trigger->isLate())
            ->flatMap(fn (Trigger $trigger) => $trigger->detect($data, collect()))
            ->reject(fn (Detection $detection) => $detection->recipient->entryId !== null && in_array($detection->recipient->entryId, $data->setAsideEntryIds(), true))
            ->values();

        $detections = $this->fairness->apply($detections, $catalogue);
        $detections = $this->crossCheck->apply($detections, $catalogue, $data);
        $superseders = $this->weekendSuperseders($data, $catalogue);
        $detections = $this->cascade->apply($detections->concat($superseders), $catalogue)
            ->reject(fn (Detection $detection) => $catalogue[$detection->achievementKey]->scope !== AwardScope::Session)
            ->values();
        $detections = $this->sensitivity->apply($detections, $catalogue, $data);
        $detections = $this->stacking->apply($detections, $catalogue);

        foreach ($triggers->filter(fn (Trigger $trigger) => $trigger->isLate()) as $trigger) {
            $detections = $detections->concat($trigger->detect($data, $detections))->values();
        }

        return $this->sensitivity->apply($detections, $catalogue, $data);
    }

    /**
     * The settled detections of a round's weekend achievements, each with the
     * session it is decided after (its anchor). An achievement whose deciding
     * session has not ended yet is undecided: it is left out, and its stored
     * awards stay as they are.
     *
     * @return array{detections: Collection<int, Detection>, anchors: array<string, SessionData>, undecided: list<string>}
     */
    public function detectWeekend(Round $round, ?CarbonImmutable $now = null): array
    {
        $catalogue = $this->catalogue();
        $sessions = $round->raceSessions()->get();
        $now ??= CarbonImmutable::now();
        $loaded = [];
        $anchors = [];
        $undecided = [];

        foreach ($catalogue->filter(fn (Achievement $achievement) => $achievement->isDecidedOver(AwardScope::Weekend)) as $key => $achievement) {
            $deciding = $achievement->decidingSession($sessions);

            if ($deciding === null || ! collect($this->triggers())->has($key)) {
                continue;
            }

            // One SessionData per session, so each session's facts are built once.
            $data = $loaded[$deciding->id] ??= $loaded === [] ? SessionData::load($deciding, $this->classifier) : collect($loaded)->first()->roundSession($deciding->type);

            if ($data->endsAt()->greaterThan($now)) {
                $undecided[] = $key;
            } else {
                $anchors[$key] = $data;
            }
        }

        $allowed = collect($anchors)->filter(fn (SessionData $data, string $key) => $this->requirements->allows($key, $data, $data->weekendSessions($this->sessionTypes($catalogue[$key]))));
        $triggers = collect($this->triggers())->intersectByKeys($allowed);

        $detections = $triggers
            ->reject(fn (Trigger $trigger) => $trigger->isLate())
            ->flatMap(fn (Trigger $trigger, string $key) => $trigger->detect($anchors[$key], collect())->map(fn (Detection $detection) => $this->onAnchor($detection, $anchors[$key])))
            ->values();

        // The rules read the round's sensitivity and entries, the same from any of its sessions.
        $data = collect($anchors)->last();

        if ($data === null) {
            return ['detections' => collect(), 'anchors' => [], 'undecided' => $undecided];
        }

        $detections = $this->fairness->apply($detections, $catalogue);
        $detections = $this->cascade->apply($detections, $catalogue);
        $detections = $this->sensitivity->apply($detections, $catalogue, $data);
        $detections = $this->stacking->apply($detections, $catalogue);

        foreach ($triggers->filter(fn (Trigger $trigger) => $trigger->isLate()) as $key => $trigger) {
            $detections = $detections->concat($trigger->detect($anchors[$key], $detections))->values();
        }

        return ['detections' => $this->sensitivity->apply($detections, $catalogue, $data), 'anchors' => $anchors, 'undecided' => $undecided];
    }

    /**
     * The detections of a season, once its final race is final; null before that.
     *
     * @return Collection<int, Detection>|null
     */
    public function detectSeason(SeasonData $data, ?CarbonImmutable $now = null): ?Collection
    {
        if (! $data->isFinal($now ?? CarbonImmutable::now())) {
            return null;
        }

        $catalogue = $this->catalogue();
        $detections = collect($this->triggers())
            ->filter(fn (Trigger|SeasonTrigger $trigger, string $key) => $trigger instanceof SeasonTrigger
                && $catalogue->has($key) && $catalogue[$key]->isDecidedOver(AwardScope::Season)
                && $this->requirements->allowsSeason($key, $data->year()))
            ->flatMap(fn (SeasonTrigger $trigger) => $trigger->detectSeason($data))
            ->values();

        return $this->stacking->apply($detections, $catalogue);
    }

    /**
     * Detect and store a session's awards. A dry run rolls everything back.
     *
     * @return Stats Per achievement key
     */
    public function run(RaceSession $session, bool $dryRun = false, ?CarbonImmutable $now = null): array
    {
        $data = SessionData::load($session, $this->classifier);
        $sessionIsFinal = $this->finality->sessionIsFinal($data, $now);

        return $this->transaction($dryRun, function () use ($data, $session, $sessionIsFinal, $now) {
            $this->sensitivity->propose($data);
            $detections = $this->detect($data);
            $round = $this->sensitivity->of($data);
            $stats = $this->store(
                context: ['scope' => AwardScope::Session, 'key' => "session:{$session->id}", 'season_id' => $session->round->season_id],
                detections: $detections,
                judge: fn (Detection $detection, ?int $driverId, Achievement $achievement) => $this->finality->judge($detection, $data, $sessionIsFinal, $driverId, $achievement),
                anchorOf: fn () => $data,
                entryOf: fn (?int $entryId) => $data->entry($entryId),
                isHistoric: $this->isHistoric($session->round),
                round: $round,
            );
            $session->update(['achievements_checked_at' => $now ?? now()]);

            return $stats;
        });
    }

    /**
     * Detect and store a round's weekend awards. Run it after the round's
     * sessions: Nothing to See Here reads their stored awards.
     *
     * @return Stats Per achievement key
     */
    public function runWeekend(Round $round, bool $dryRun = false, ?CarbonImmutable $now = null): array
    {
        return $this->transaction($dryRun, function () use ($round, $now) {
            ['detections' => $detections, 'anchors' => $anchors, 'undecided' => $undecided] = $this->detectWeekend($round, $now);
            $final = collect($anchors)->map(fn (SessionData $data) => $this->finality->sessionIsFinal($data, $now));
            $any = collect($anchors)->first();

            return $this->store(
                context: ['scope' => AwardScope::Weekend, 'key' => "round:{$round->id}", 'season_id' => $round->season_id],
                detections: $detections,
                judge: fn (Detection $detection, ?int $driverId, Achievement $achievement) => $this->finality->judge($detection, $anchors[$detection->achievementKey], $final[$detection->achievementKey], $driverId, $achievement),
                anchorOf: fn (Detection $detection) => $anchors[$detection->achievementKey],
                entryOf: fn (?int $entryId) => $any?->entry($entryId),
                isHistoric: $this->isHistoric($round),
                round: $any === null ? Sensitivity::forRound($round->id) : $this->sensitivity->of($any),
                undecided: $this->catalogue()->whereIn('key', $undecided)->pluck('id')->values()->all(),
            );
        });
    }

    /**
     * Detect and store a season's awards, once its final race is final.
     * Run it after the season's rounds and its OOFscore: the Oofies read both.
     *
     * @return Stats|null Per achievement key; null while the season is not final
     */
    public function runSeason(Season $season, bool $dryRun = false, ?CarbonImmutable $now = null): ?array
    {
        return $this->transaction($dryRun, function () use ($season, $now) {
            $data = new SeasonData($season, $this->classifier);
            $detections = $this->detectSeason($data, $now);

            if ($detections === null) {
                return null;
            }

            $finalRound = $data->finalRound();

            return $this->store(
                context: ['scope' => AwardScope::Season, 'key' => "season:{$season->id}", 'season_id' => $season->id],
                detections: $detections,
                judge: fn (Detection $detection) => $this->finality->judgeSeason($detection),
                anchorOf: fn () => null,
                entryOf: fn (?int $entryId) => $data->entry($entryId),
                isHistoric: $finalRound !== null && $this->isHistoric($finalRound),
                round: Sensitivity::NONE,
            );
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function transaction(bool $dryRun, Closure $work): mixed
    {
        DB::beginTransaction();

        try {
            $result = $work();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $result;
    }

    /**
     * Store a context's detections: create and update its awards, and remove
     * what is no longer found, except awards of undecided achievements.
     *
     * @param  array{scope: AwardScope, key: string, season_id: int}  $context
     * @param  Collection<int, Detection>  $detections
     * @param  Closure(Detection, ?int, Achievement): array{0: AwardStatus, 1: AwardReviewReason|null}  $judge
     * @param  Closure(Detection): ?SessionData  $anchorOf  The session the award is anchored to
     * @param  Closure(?int): ?DriverEntry  $entryOf
     * @param  array{sensitive: bool, blocked_round: bool, blocked_drivers: list<int>, blocked_season_teams: list<int>, note: string|null}  $round
     * @param  list<int>  $undecided  IDs of achievements not decided yet, whose awards stay as they are
     * @return Stats
     */
    private function store(array $context, Collection $detections, Closure $judge, Closure $anchorOf, Closure $entryOf, bool $isHistoric, array $round, array $undecided = []): array
    {
        $catalogue = $this->catalogue();
        $variants = AchievementVariant::query()->get()->keyBy(fn (AchievementVariant $variant) => "{$variant->achievement_id}|{$variant->driver_id}");
        $existing = AchievementAward::query()
            ->where('context_key', $context['key'])
            ->whereNotIn('achievement_id', $undecided)
            ->get()
            ->keyBy(fn (AchievementAward $award) => "{$award->achievement_id}|{$award->instance_key}");
        $stats = [];
        $kept = [];

        foreach ($detections as $detection) {
            $achievement = $catalogue[$detection->achievementKey];
            $stat = &$stats[$achievement->key];
            $stat ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
            $stat['found']++;

            $recipient = $detection->recipient;
            $driverId = $entryOf($recipient->entryId)?->driver_id;
            [$status, $reviewReason] = $judge($detection, $driverId, $achievement);
            $key = "{$achievement->id}|{$detection->awardInstanceKey()}";
            $award = $existing->get($key) ?? new AchievementAward([
                'scope' => $context['scope'],
                'context_key' => $context['key'],
                'season_id' => $context['season_id'],
                'race_session_id' => $anchorOf($detection)?->session->id,
                'recipient_type' => $recipient->type,
                'achievement_id' => $achievement->id,
                'instance_key' => $detection->awardInstanceKey(),
            ]);
            $kept[$key] = true;

            $award->fill([
                'event_key' => $detection->eventKey,
                'detail' => $detection->detail,
                'confidence' => $detection->confidence,
            ]);

            // MySQL stores JSON objects with their keys reordered and whole floats as integers, so compare them canonically.
            if (! $award->exists || self::canonical($award->evidence) !== self::canonical($detection->evidence)) {
                $award->evidence = $detection->evidence;
            }

            // A person's decision stands: the car they picked, the status and their note.
            if (! $award->isDecided()) {
                // A team award names its season team; a driver award gets its team from the car.
                if ($recipient->is(RecipientType::Team)) {
                    $award->season_team_id = $recipient->seasonTeamId;
                }

                // In a historic session nobody reviews what would wait for review, unless it was handed back.
                $suppressAsHistoric = $isHistoric && $status === AwardStatus::Pending && $reviewReason !== null && $award->handed_back_at === null;

                $award->fill([
                    'driver_entry_id' => $recipient->entryId,
                    'driver_id' => $driverId,
                    'status' => $suppressAsHistoric ? AwardStatus::Suppressed : $status,
                    'review_reason' => $reviewReason,
                    'decided_at' => $suppressAsHistoric ? $award->decided_at ?? now() : null,
                    'decision_note' => $suppressAsHistoric ? AchievementAward::HISTORIC_NOTE : null,
                ]);
            }

            if ($round['sensitive']) {
                $award->fill(['is_sensitive' => true, 'sensitivity_note' => $round['note']]);
            }

            $variant = $award->driver_id === null ? null : $variants->get("{$achievement->id}|{$award->driver_id}");
            $award->variant_name = $variant?->name;
            $award->achievement_variant_id = $variant?->id;

            if (! $award->exists) {
                $stat['created']++;
            } elseif ($award->isDirty()) {
                $stat['updated']++;
            }

            $stat['pending'] += $award->status === AwardStatus::Pending ? 1 : 0;
            $award->save();
            unset($stat);
        }

        foreach ($existing as $key => $award) {
            if (isset($kept[$key])) {
                continue;
            }

            $achievement = $catalogue->firstWhere('id', $award->achievement_id);
            $blocked = Sensitivity::blocks($round, $award);
            $withheld = $achievement !== null && ! Sensitivity::allowsAward($round, $achievement, $award);

            // What the engine made itself goes; what a person decided is suppressed by the system, keeping their decision.
            $outcome = match (true) {
                ! $award->isDecided() => 'delete',
                ($blocked || $withheld) && $award->decision_note !== Sensitivity::SYSTEM_NOTE => 'suppress',
                default => null,
            };

            if ($outcome === 'delete') {
                $award->delete();
            } elseif ($outcome === 'suppress') {
                $award->suppressBySystem($round['note']);
            }

            if ($outcome !== null) {
                $achievementKey = $achievement?->key ?? (string) $award->achievement_id;
                $stats[$achievementKey] ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
                $stats[$achievementKey]['removed']++;
            }
        }

        // Whatever stays of a sensitive round is flagged, decided or not; the flag never clears.
        if ($round['sensitive']) {
            AchievementAward::query()
                ->where('context_key', $context['key'])
                ->where('is_sensitive', false)
                ->update(['is_sensitive' => true, 'sensitivity_note' => $round['note'], 'updated_at' => now()]);
        }

        ksort($stats);

        return $stats;
    }

    /**
     * Detections of the weekend achievements that supersede an achievement
     * of this session, found as the weekend will find them, for the
     * session's cascade only.
     *
     * @param  Collection<string, Achievement>  $catalogue
     * @return Collection<int, Detection>
     */
    private function weekendSuperseders(SessionData $data, Collection $catalogue): Collection
    {
        $here = $catalogue->filter(fn (Achievement $achievement) => $achievement->appliesTo($data->type()))->keys();

        return $catalogue
            ->filter(fn (Achievement $achievement, string $key) => $achievement->isDecidedOver(AwardScope::Weekend)
                && in_array($data->type()->value, $achievement->session_types, true)
                && $here->intersect($achievement->supersedes)->isNotEmpty())
            ->flatMap(function (Achievement $achievement, string $key) use ($data) {
                $trigger = $this->trigger($key);
                $deciding = $achievement->decidingSession($data->session->round->raceSessions()->get());
                $anchor = $deciding === null ? null : $data->roundSession($deciding->type);

                if (! $trigger instanceof Trigger || $trigger->isLate() || $anchor === null
                    || ! $this->requirements->allows($key, $anchor, $anchor->weekendSessions($this->sessionTypes($achievement)))) {
                    return [];
                }

                return $trigger->detect($anchor, collect());
            })
            ->values();
    }

    /**
     * A weekend detection for a driver on the car they drove in the deciding
     * session, when they drove in it.
     */
    private function onAnchor(Detection $detection, SessionData $anchor): Detection
    {
        if (! $detection->recipient->is(RecipientType::Driver)) {
            return $detection;
        }

        $driverId = $anchor->entry($detection->recipient->entryId)?->driver_id;
        $entry = $driverId === null ? null : $anchor->entryOfDriver($driverId);

        return $entry === null || $entry->id === $detection->recipient->entryId ? $detection : $detection->with(['recipient' => Recipient::driver($entry->id)]);
    }

    /**
     * @return list<SessionType>
     */
    private function sessionTypes(Achievement $achievement): array
    {
        return array_map(SessionType::from(...), $achievement->session_types);
    }

    /**
     * Before the review date nobody reviews what would wait for review.
     */
    private function isHistoric(Round $round): bool
    {
        return $round->date->lt(CarbonImmutable::parse(config('achievements.review_from')));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => match (true) {
            is_array($item) => self::canonical($item),
            is_float($item) && floor($item) === $item => (int) $item,
            default => $item,
        }, $value);
    }

    /**
     * @return Collection<string, Achievement> Keyed by key
     */
    private function catalogue(): Collection
    {
        return Achievement::query()->get()->keyBy('key');
    }
}
