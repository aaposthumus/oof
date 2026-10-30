<?php

namespace Tests\Concerns;

use App\Achievements\AchievementEngine;
use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\SessionData;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\QualifyingResult;
use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Round;
use App\Models\Season;
use App\Models\SeasonTeam;
use App\RaceControl\MessageClassifier;
use Carbon\CarbonImmutable;
use Database\Seeders\AchievementSeeder;
use Illuminate\Support\Collection;

/**
 * Small sessions for the achievements engine: cars, results and race
 * control messages, with every car a message names linked to it.
 */
trait BuildsSessions
{
    private ?CarbonImmutable $clock = null;

    protected function catalogue(): void
    {
        $this->seed(AchievementSeeder::class);
    }

    protected function round(int $year = 2024, int $roundNumber = 5, array $attributes = []): Round
    {
        $season = Season::firstOrCreate(['year' => $year]);

        return Round::factory()->for($season)->create(['round_number' => $roundNumber, 'name' => 'Miami Grand Prix', 'date' => "{$year}-05-05", 'time' => '20:00:00', ...$attributes]);
    }

    protected function raceSession(SessionType $type = SessionType::Race, ?Round $round = null): RaceSession
    {
        $round ??= $this->round();

        return RaceSession::factory()->for($round)->create(['type' => $type, 'scheduled_date' => $round->date, 'scheduled_time' => '20:00:00']);
    }

    protected function car(RaceSession $session, int $number, ?SeasonTeam $team = null, ?Driver $driver = null): DriverEntry
    {
        return DriverEntry::factory()->for($session)->create([
            'car_number' => $number,
            'season_team_id' => $team?->id ?? SeasonTeam::factory()->create(['season_id' => $session->round->season_id])->id,
            'driver_id' => $driver?->id ?? Driver::factory()->create()->id,
        ]);
    }

    /**
     * A message issued a minute after the previous one, linked to every car
     * named in it as "<number> (<ABC>)".
     */
    protected function message(RaceSession $session, string $text, array $attributes = []): RaceControlMessage
    {
        $this->clock = ($this->clock ?? CarbonImmutable::parse('2024-05-05 20:00:00'))->addMinute();
        preg_match_all('/(?<![^\s,])(\d{1,2}) \([A-Z]{3}\)/', $text, $matches);
        $cars = array_map('intval', $matches[1]);

        $message = RaceControlMessage::factory()->for($session)->create([
            'issued_at' => $this->clock,
            'category' => 'Other',
            'flag' => null,
            'scope' => null,
            'message' => $text,
            'car_number' => count($cars) === 1 ? $cars[0] : null,
            'car_numbers' => $cars === [] ? null : $cars,
            ...$attributes,
        ]);

        $entries = DriverEntry::query()->where('race_session_id', $session->id)->whereIn('car_number', $cars)->get();
        $message->involvedEntries()->attach($entries->mapWithKeys(fn (DriverEntry $entry) => [$entry->id => ['car_number' => $entry->car_number]])->all());

        return $message;
    }

    protected function resultFor(DriverEntry $entry, array $attributes = []): Result
    {
        return Result::factory()->for($entry)->create($attributes);
    }

    protected function qualifying(DriverEntry $entry, int $position, ?int $q1 = null, ?int $q2 = null, ?int $q3 = null): QualifyingResult
    {
        return QualifyingResult::create(['driver_entry_id' => $entry->id, 'position' => $position, 'q1_millis' => $q1, 'q2_millis' => $q2, 'q3_millis' => $q3]);
    }

    protected function data(RaceSession $session): SessionData
    {
        return SessionData::load($session->fresh(), app(MessageClassifier::class));
    }

    /**
     * @param  class-string<Detector>  $detector
     * @return Collection<int, Detection>
     */
    protected function detect(string $detector, RaceSession $session): Collection
    {
        return app($detector)->detect($this->data($session));
    }

    /**
     * Run the engine as if well after the session, so it is final.
     *
     * @return array<string, array<string, int>>
     */
    protected function runEngine(RaceSession $session, ?CarbonImmutable $now = null): array
    {
        return app(AchievementEngine::class)->run($session->fresh(), now: $now ?? CarbonImmutable::parse('2024-06-01'));
    }

    /**
     * @return Collection<int, AchievementAward>
     */
    protected function awards(?string $key = null): Collection
    {
        return AchievementAward::query()
            ->with('achievement')
            ->when($key, fn ($query) => $query->whereRelation('achievement', 'key', $key))
            ->orderBy('id')
            ->get();
    }
}
