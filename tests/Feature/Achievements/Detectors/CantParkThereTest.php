<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Enums\SessionType;
use App\Models\AchievementAward;
use App\Models\Driver;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Can't Park There is a penalised car out of position on the grid (Article
 * 48.1 c): only the grid position wordings, never the generic starting
 * procedure infringement or "out of position at the safety car line".
 */
class CantParkThereTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function gridPositionWordings(): array
    {
        return [
            'false start out of position' => ['FALSE START - OUT OF POSITION'],
            'inaccurate grid position' => ['INACCURATE GRID POSITION'],
            'incorrect starting location' => ['INCORRECT STARTING LOCATION'],
        ];
    }

    #[DataProvider('gridPositionWordings')]
    public function test_a_penalised_grid_position_offence_is_cant_park_there_and_not_itchy_feet(string $offence): void
    {
        $race = $this->penalisedRace($offence);

        $this->assertCount(1, $this->detect('cant_park_there', $race));
        $this->assertCount(0, $this->detect('itchy_feet', $race));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherWordings(): array
    {
        return [
            'generic starting procedure infringement' => ['STARTING PROCEDURE INFRINGEMENT'],
            'out of position at the safety car line' => ['STARTING PROCEDURE INFRINGEMENT - OUT OF POSITION AT SAFETY CAR LINE'],
            'bare out of position' => ['OUT OF POSITION'],
            'jump start' => ['FALSE START - MOVING BEFORE SIGNAL'],
            'bare false start' => ['FALSE START'],
        ];
    }

    #[DataProvider('otherWordings')]
    public function test_other_wordings_are_not_cant_park_there(string $offence): void
    {
        $this->assertCount(0, $this->detect('cant_park_there', $this->penalisedRace($offence)));
    }

    public function test_a_car_that_is_only_noted_or_investigated_gets_nothing(): void
    {
        $race = $this->raceSession();
        $this->car($race, 4);
        $this->message($race, 'INCIDENT INVOLVING CAR 4 (NOR) NOTED - FALSE START - OUT OF POSITION');
        $this->message($race, 'FIA STEWARDS: INCIDENT INVOLVING CAR 4 (NOR) REVIEWED NO FURTHER INVESTIGATION - FALSE START - OUT OF POSITION');

        $this->assertCount(0, $this->detect('cant_park_there', $race));
    }

    /**
     * @return array<string, array{int, int, string}>
     */
    public static function rulings(): array
    {
        return [
            'perez in Mexico City 2024' => [2024, 20, 'perez'],
            'piastri in Las Vegas 2024' => [2024, 22, 'piastri'],
        ];
    }

    #[DataProvider('rulings')]
    public function test_a_bare_false_start_the_stewards_ruled_out_of_position_is_cant_park_there(int $year, int $round, string $driver): void
    {
        $race = $this->penalisedRace('FALSE START', $year, $round, $driver);

        $this->assertCount(1, $this->detect('cant_park_there', $race));
        $this->assertCount(0, $this->detect('itchy_feet', $race));
    }

    public function test_it_replaces_the_penalty_achievement_and_shows_the_penalty(): void
    {
        $this->catalogue();
        $race = $this->penalisedRace('FALSE START - OUT OF POSITION');

        $this->runEngine($race);

        $awards = $this->awards();
        $this->assertSame(['cant_park_there'], $awards->pluck('achievement.key')->intersect(['cant_park_there', 'itchy_feet', 'high_five'])->values()->all());
        $this->assertSame('5 s', $awards->firstWhere('achievement.key', 'cant_park_there')->detail);
        $this->assertSame('Can\'t Park There · 5 s', $awards->firstWhere('achievement.key', 'cant_park_there')->title());
    }

    public function test_a_heavier_penalty_keeps_its_own_stamp_beside_it(): void
    {
        $this->catalogue();
        $race = $this->raceSession();
        $this->car($race, 11);
        $this->message($race, 'INCIDENT INVOLVING CAR 11 (PER) NOTED - FALSE START - OUT OF POSITION (15:03:08)');
        $this->message($race, 'FIA STEWARDS: DRIVE THROUGH PENALTY FOR CAR 11 (PER) - FALSE START - OUT OF POSITION (15:03:08)');

        $this->runEngine($race);

        $awards = $this->awards();
        $this->assertEqualsCanonicalizing(['cant_park_there', 'drive_thru'], $awards->pluck('achievement.key')->intersect(['cant_park_there', 'drive_thru', 'high_five'])->values()->all());
        $this->assertSame('drive-through', $awards->firstWhere('achievement.key', 'drive_thru')->detail);
    }

    public function test_a_stop_and_go_keeps_its_own_stamp_beside_a_jump_start(): void
    {
        $this->catalogue();
        $race = $this->raceSession();
        $this->car($race, 4);
        $this->message($race, 'INCIDENT INVOLVING CAR 4 (NOR) NOTED - FALSE START - MOVING BEFORE SIGNAL');
        $this->message($race, 'FIA STEWARDS: STOP-AND-GO PENALTY FOR CAR 4 (NOR) - FALSE START - MOVING BEFORE SIGNAL');

        $this->runEngine($race);

        $keys = $this->awards()->pluck('achievement.key');
        $this->assertEqualsCanonicalizing(['itchy_feet', 'red_light_green_light'], $keys->intersect(['itchy_feet', 'red_light_green_light', 'high_five'])->values()->all());
    }

    public function test_a_gold_penalty_is_still_replaced(): void
    {
        $this->catalogue();
        $race = $this->raceSession();
        $this->car($race, 4);
        $this->message($race, 'INCIDENT INVOLVING CAR 4 (NOR) NOTED - INACCURATE GRID POSITION');
        $this->message($race, 'FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 4 (NOR) - INACCURATE GRID POSITION');

        $this->runEngine($race);

        $keys = $this->awards()->pluck('achievement.key');
        $this->assertSame(['cant_park_there'], $keys->intersect(['cant_park_there', 'perfect_ten', 'high_five'])->values()->all());
    }

    public function test_itchy_feet_shows_its_penalty_too(): void
    {
        $this->catalogue();

        $this->runEngine($this->penalisedRace('FALSE START - MOVING BEFORE SIGNAL'));

        $awards = $this->awards();
        $this->assertSame(['itchy_feet'], $awards->pluck('achievement.key')->intersect(['cant_park_there', 'itchy_feet', 'high_five'])->values()->all());
        $this->assertSame('Itchy Feet · 5 s', $awards->firstWhere('achievement.key', 'itchy_feet')->title());
    }

    /**
     * @return Collection<int, AchievementAward>
     */
    private function awards(): Collection
    {
        return AchievementAward::query()->with('achievement')->get();
    }

    private function penalisedRace(string $offence, int $year = 2024, int $round = 5, string $driver = 'someone'): RaceSession
    {
        $race = $this->raceSession(SessionType::Race, $this->round($year, $round));
        $this->car($race, 4, driver: Driver::factory()->create(['jolpica_driver_id' => $driver]));
        $this->message($race, "INCIDENT INVOLVING CAR 4 (NOR) NOTED - {$offence}");
        $this->message($race, "FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - {$offence}");

        return $race;
    }
}
