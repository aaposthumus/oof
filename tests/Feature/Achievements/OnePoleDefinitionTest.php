<?php

namespace Tests\Feature\Achievements;

use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Facts;
use App\Achievements\Facts\Predicates;
use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * The engine has one definition of pole (decided by the owner on
 * 22 November 2026, see Poles): the pole award (Early Bird, or Plot Twist
 * for a surprise), took_pole (Clean Sweep, Peaked Too Soon) and the first
 * pole of First Time for Everything always name the same driver.
 */
class OnePoleDefinitionTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogue();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function entered(RaceSession $race, int $number, Driver $driver, array $result): DriverEntry
    {
        $entry = $this->car($race, $number, null, $driver);
        $this->resultFor($entry, ['classification_order' => $number, 'status' => 'Finished', 'laps_completed' => 50, ...$result]);

        return $entry;
    }

    private function driver(string $jolpicaId): Driver
    {
        return Driver::factory()->create(['jolpica_driver_id' => $jolpicaId]);
    }

    /**
     * Rounds of every kind, each with the Jolpica ID of the official pole sitter (null: nobody).
     *
     * @return array<string, array{string}>
     */
    public static function rounds(): array
    {
        return [
            'qualifying P1 who starts lower after a grid penalty' => ['qualifying'],
            'grid only' => ['grid'],
            'car swap in the race (Argentina 1956)' => ['car_swap'],
            'shared car on grid 1 (Argentina 1955)' => ['shared_car'],
            'unlisted tie on grid 1' => ['unlisted_tie'],
        ];
    }

    /**
     * @return array{0: RaceSession, 1: string|null}
     */
    private function build(string $kind): array
    {
        $won = ['position' => 1, 'position_text' => '1', 'points' => 8];
        $second = ['position' => 2, 'position_text' => '2', 'points' => 6];
        $retired = ['position' => null, 'position_text' => 'R', 'is_classified' => false, 'status' => 'Engine', 'laps_completed' => 20];

        switch ($kind) {
            case 'qualifying':
                $race = $this->raceSession(SessionType::Race, $this->round(2024, 5));
                $qualifying = $this->raceSession(SessionType::Qualifying, $race->round);
                $penalised = $this->driver('penalised');
                $other = $this->driver('other');
                $this->qualifying($this->car($qualifying, 1, null, $penalised), 1, 90_000, 89_000, 88_000);
                $this->qualifying($this->car($qualifying, 2, null, $other), 2, 90_100, 89_100, 88_100);
                $this->entered($race, 1, $penalised, ['grid' => 6, ...$second]);
                $this->entered($race, 2, $other, ['grid' => 1, ...$won]);

                return [$race, 'penalised'];

            case 'grid':
                $race = $this->raceSession(SessionType::Race, $this->round(1975, 3));
                $this->entered($race, 1, $this->driver('lauda'), ['grid' => 1, ...$second]);
                $this->entered($race, 2, $this->driver('hunt'), ['grid' => 2, ...$won]);

                return [$race, 'lauda'];

            case 'car_swap':
                $race = $this->raceSession(SessionType::Race, $this->round(1956, 1));
                $fangio = $this->driver('fangio');
                $this->entered($race, 30, $fangio, ['grid' => 1, ...$retired]);
                $this->entered($race, 34, $fangio, ['grid' => 8, ...$won]);
                $this->entered($race, 34, $this->driver('musso'), ['grid' => 8, ...$won]);

                return [$race, 'fangio'];

            case 'shared_car':
                $race = $this->raceSession(SessionType::Race, $this->round(1955, 1));

                foreach (['farina', 'gonzalez', 'trintignant'] as $index => $jolpicaId) {
                    $this->entered($race, 12, $this->driver($jolpicaId), ['grid' => 1, ...$second, 'classification_order' => 2 + $index]);
                }

                $this->entered($race, 2, $this->driver('fangio'), ['grid' => 3, ...$won]);

                return [$race, 'gonzalez'];

            default:
                $race = $this->raceSession(SessionType::Race, $this->round(1954, 4));
                $this->entered($race, 1, $this->driver('first'), ['grid' => 1, ...$won]);
                $this->entered($race, 1, $this->driver('second'), ['grid' => 1, ...$won]);

                return [$race, null];
        }
    }

    /**
     * @return list<string>
     */
    private function poleAwardDrivers(): array
    {
        return AchievementAward::query()->with(['achievement', 'driver'])->get()
            ->filter(fn (AchievementAward $award) => in_array($award->achievement->key, ['early_bird', 'plot_twist'], true))
            ->map(fn (AchievementAward $award) => $award->driver->jolpica_driver_id)
            ->values()
            ->all();
    }

    #[DataProvider('rounds')]
    public function test_took_pole_holds_for_exactly_the_driver_with_the_pole_award(string $kind): void
    {
        [$race, $official] = $this->build($kind);
        $qualifying = RaceSession::query()->where('round_id', $race->round_id)->where('type', SessionType::Qualifying)->first();

        if ($qualifying !== null) {
            $this->runEngine($qualifying);
        }

        $this->runEngine($race);

        $data = $this->data($race);
        $tookPole = app(Facts::class)->of($data, 'result')
            ->filter(fn (Fact $fact) => app(Predicates::class)->test('took_pole', $fact, $data))
            ->map(fn (Fact $fact) => $data->entry($fact->entryId)->driver->jolpica_driver_id)
            ->unique()
            ->values()
            ->all();

        $this->assertSame($official === null ? [] : [$official], $this->poleAwardDrivers());
        $this->assertSame($this->poleAwardDrivers(), $tookPole);
    }

    public function test_clean_sweep_and_early_bird_name_the_same_driver_in_a_shared_car(): void
    {
        $race = $this->raceSession(SessionType::Race, $this->round(1953, 8));
        $fangio = $this->driver('fangio');
        $bonetto = $this->driver('bonetto');
        $this->entered($race, 32, $bonetto, ['grid' => 1, 'position' => 1, 'position_text' => '1', 'points' => 4]);
        $this->entered($race, 32, $fangio, ['grid' => 1, 'position' => 1, 'position_text' => '1', 'points' => 4]);
        config(['curated_clean_sweeps.races' => [['season' => 1953, 'round' => 8, 'driver' => 'fangio'], ['season' => 1953, 'round' => 8, 'driver' => 'bonetto']]]);

        $this->runEngine($race);

        // Both are credited with the win, but only the official pole sitter took pole.
        $this->assertSame([$fangio->id], $this->awards('early_bird')->pluck('driver_id')->all());
        $this->assertSame([$fangio->id], $this->awards('clean_sweep')->pluck('driver_id')->all());
        $this->assertSame([$bonetto->id], $this->awards('did_the_thing')->pluck('driver_id')->all());
    }

    public function test_the_first_pole_goes_to_the_official_pole_sitter_only_and_counts_from_then_on(): void
    {
        $first = $this->raceSession(SessionType::Race, $this->round(1955, 1));

        foreach (['farina', 'gonzalez', 'trintignant'] as $index => $jolpicaId) {
            $this->entered($first, 12, $this->driver($jolpicaId), ['grid' => 1, 'position' => 2, 'position_text' => '2', 'points' => 2, 'classification_order' => 2 + $index]);
        }

        $this->runEngine($first);
        $firstPoles = $this->awards('first_time_for_everything')->filter(fn (AchievementAward $award) => str_contains((string) $award->detail, 'First pole'));
        $this->assertSame(['gonzalez'], $firstPoles->map(fn (AchievementAward $award) => $award->driver->jolpica_driver_id)->values()->all());

        // A later grid 1 for Farina is his first pole, as the shared car's was not his.
        $later = $this->raceSession(SessionType::Race, $this->round(1955, 2, ['date' => '1955-06-05']));
        $this->entered($later, 10, Driver::firstWhere('jolpica_driver_id', 'farina'), ['grid' => 1, 'position' => 3, 'position_text' => '3', 'points' => 4]);
        $this->entered($later, 11, Driver::firstWhere('jolpica_driver_id', 'gonzalez'), ['grid' => 2, 'position' => 4, 'position_text' => '4', 'points' => 3]);

        $this->runEngine($later);

        $this->assertTrue($this->awards('first_time_for_everything')->contains(fn (AchievementAward $award) => $award->race_session_id === $later->id
            && $award->driver->jolpica_driver_id === 'farina' && str_contains((string) $award->detail, 'First pole')));
    }
}
