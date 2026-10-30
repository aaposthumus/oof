<?php

namespace App\Achievements;

use App\Achievements\Rules\Cascade;
use App\Achievements\Rules\CrossCheck;
use App\Achievements\Rules\Fairness;
use App\Achievements\Rules\Finality;
use App\Achievements\Rules\Stacking;
use App\Enums\AwardStatus;
use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\AchievementVariant;
use App\Models\RaceSession;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the detectors on a session, applies the rules from the spec and
 * stores the awards. Re-running a session updates its awards in place,
 * never duplicates them, and never changes what a person decided.
 *
 * Order: detect, fairness, cross-check, cascade, stacking, the late
 * detectors, then finality.
 */
class AchievementEngine
{
    /**
     * @var list<class-string<Detector|LateDetector>>
     */
    public const DETECTORS = [
        // Race control: offences with a penalty, penalties and flags
        Detectors\BumperCarsDetector::class,
        Detectors\EarlyBirdDetector::class,
        Detectors\SelectiveHearingDetector::class,
        Detectors\RecalculatingDetector::class,
        Detectors\LaneDisciplineDetector::class,
        Detectors\SmileForTheCameraDetector::class,
        Detectors\RoadHogDetector::class,
        Detectors\ForgotToIndicateDetector::class,
        Detectors\DidntSeeTheSignDetector::class,
        Detectors\QueueJumperDetector::class,
        Detectors\FailedTheDrivingTestDetector::class,
        Detectors\ReleasedIntoTheWildDetector::class,
        Detectors\SundayDriverDetector::class,
        Detectors\HighFiveDetector::class,
        Detectors\PerfectTenDetector::class,
        Detectors\DriveThruDetector::class,
        Detectors\RedLightGreenLightDetector::class,
        Detectors\ItWasLikeThatDetector::class,
        Detectors\MeatballSubDetector::class,
        Detectors\ExpelledDetector::class,
        Detectors\ColouringOutsideTheLinesDetector::class,
        Detectors\NoteSentHomeDetector::class,
        Detectors\MirrorSignalManoeuvreDetector::class,
        Detectors\WholeClassDetentionDetector::class,
        Detectors\CalledTheTowTruckDetector::class,
        // Results
        Detectors\LappedDogDetector::class,
        Detectors\GoodBoyDetector::class,
        Detectors\ForgotTheKeysDetector::class,
        Detectors\EarlyLunchDetector::class,
        Detectors\HomeBeforeDinnerDetector::class,
        Detectors\SentToYourRoomDetector::class,
        Detectors\ReverseGearDetector::class,
        Detectors\AllDressedUpDetector::class,
        Detectors\TurnedOffTheLightsDetector::class,
        // Pit stops
        Detectors\TableForOneDetector::class,
        Detectors\FineDiningDetector::class,
        // Qualifying
        Detectors\FamilyFeudDetector::class,
        Detectors\ByAWhiskerDetector::class,
        Detectors\SoCloseDetector::class,
        Detectors\HoldingTheDoorDetector::class,
        // Practice
        Detectors\OvertimeDetector::class,
        Detectors\PracticeMakesImperfectDetector::class,
        // Glory
        Detectors\DidTheThingDetector::class,
        Detectors\StandingRoomOnlyDetector::class,
        Detectors\PlotTwistDetector::class,
        Detectors\LotteryTicketDetector::class,
        Detectors\TookTheScenicRouteDetector::class,
        Detectors\HoggedTheLotDetector::class,
        Detectors\MathsIsOverDetector::class,
        Detectors\SpareTyreDetector::class,
        // Safety Car
        Detectors\TookTheLeadDetector::class,
        Detectors\CalledInAgainDetector::class,
        Detectors\FrequentFlyerDetector::class,
        Detectors\VirtuallyThereDetector::class,
        // After the others
        Detectors\NothingToSeeHereDetector::class,
    ];

    public function __construct(
        private Container $container,
        private MessageClassifier $classifier,
        private Fairness $fairness,
        private CrossCheck $crossCheck,
        private Cascade $cascade,
        private Stacking $stacking,
        private Finality $finality,
    ) {}

    /**
     * @return array<string, Detector|LateDetector> Keyed by achievement key
     */
    public function detectors(): array
    {
        return collect(self::DETECTORS)
            ->map(fn (string $class) => $this->container->make($class))
            ->keyBy(fn (Detector|LateDetector $detector) => $detector->key())
            ->all();
    }

    /**
     * The settled detections of a session, before they are stored.
     *
     * @return Collection<int, Detection>
     */
    public function detect(SessionData $data): Collection
    {
        $catalogue = $this->catalogue();
        $detectors = collect($this->detectors())
            ->filter(fn ($detector, string $key) => $catalogue->has($key) && $catalogue[$key]->appliesTo($data->type()));

        $detections = $detectors
            ->filter(fn ($detector) => $detector instanceof Detector)
            ->flatMap(fn (Detector $detector) => $detector->detect($data))
            ->values();

        $detections = $this->fairness->apply($detections, $catalogue);
        $detections = $this->crossCheck->apply($detections, $catalogue, $data);
        $detections = $this->cascade->apply($detections, $catalogue);
        $detections = $this->stacking->apply($detections, $catalogue);

        foreach ($detectors->filter(fn ($detector) => $detector instanceof LateDetector) as $detector) {
            $detections = $detections->concat($detector->detectAfter($data, $detections))->values();
        }

        return $detections;
    }

    /**
     * Detect and store a session's awards. A dry run rolls everything back.
     *
     * @return array<string, array{found: int, created: int, updated: int, removed: int, pending: int}> Per achievement key
     */
    public function run(RaceSession $session, bool $dryRun = false, ?CarbonImmutable $now = null): array
    {
        $data = SessionData::load($session, $this->classifier);
        $detections = $this->detect($data);
        $sessionIsFinal = $this->finality->sessionIsFinal($data, $now);

        DB::beginTransaction();

        try {
            $stats = $this->store($data, $detections, $sessionIsFinal);
            $session->update(['achievements_checked_at' => $now ?? now()]);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $stats;
    }

    /**
     * @param  Collection<int, Detection>  $detections
     * @return array<string, array{found: int, created: int, updated: int, removed: int, pending: int}>
     */
    private function store(SessionData $data, Collection $detections, bool $sessionIsFinal): array
    {
        $catalogue = $this->catalogue();
        $variants = AchievementVariant::query()->get()->keyBy(fn (AchievementVariant $variant) => "{$variant->achievement_id}|{$variant->driver_id}");
        $existing = AchievementAward::query()
            ->where('race_session_id', $data->session->id)
            ->get()
            ->keyBy(fn (AchievementAward $award) => "{$award->achievement_id}|{$award->instance_key}");
        $isHistoric = $data->session->round->date->lt(CarbonImmutable::parse(config('achievements.review_from')));
        $stats = [];
        $kept = [];

        foreach ($detections as $detection) {
            $achievement = $catalogue[$detection->achievementKey];
            $stat = &$stats[$achievement->key];
            $stat ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
            $stat['found']++;

            $driverId = $data->entry($detection->driverEntryId)?->driver_id;
            [$status, $reviewReason] = $this->finality->judge($detection, $data, $sessionIsFinal, $driverId);
            $key = "{$achievement->id}|{$detection->awardInstanceKey()}";
            $award = $existing->get($key) ?? new AchievementAward([
                'race_session_id' => $data->session->id,
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
                // In a historic session nobody reviews what would wait for review, unless it was handed back.
                $suppressAsHistoric = $isHistoric && $status === AwardStatus::Pending && $reviewReason !== null && $award->handed_back_at === null;

                $award->fill([
                    'driver_entry_id' => $detection->driverEntryId,
                    'driver_id' => $driverId,
                    'status' => $suppressAsHistoric ? AwardStatus::Suppressed : $status,
                    'review_reason' => $reviewReason,
                    'decided_at' => $suppressAsHistoric ? $award->decided_at ?? now() : null,
                    'decision_note' => $suppressAsHistoric ? AchievementAward::HISTORIC_NOTE : null,
                ]);
            }

            $award->variant_name = $award->driver_id === null ? null : $variants->get("{$achievement->id}|{$award->driver_id}")?->name;

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
            if (! isset($kept[$key]) && ! $award->isDecided()) {
                $award->delete();
                $achievementKey = $catalogue->firstWhere('id', $award->achievement_id)?->key ?? (string) $award->achievement_id;
                $stats[$achievementKey] ??= ['found' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'pending' => 0];
                $stats[$achievementKey]['removed']++;
            }
        }

        ksort($stats);

        return $stats;
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
