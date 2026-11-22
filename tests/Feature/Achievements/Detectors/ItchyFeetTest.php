<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Enums\SessionType;
use App\Models\Driver;
use App\Models\RaceSession;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

/**
 * Itchy Feet is a jump start: moving before the start signal. A car out of
 * position on the grid is Can't Park There, however the stewards word it.
 */
class ItchyFeetTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function jumpStarts(): array
    {
        return [
            'moving before signal' => ['FALSE START - MOVING BEFORE SIGNAL'],
            'bare false start' => ['FALSE START'],
            'jump start' => ['JUMP START'],
        ];
    }

    #[DataProvider('jumpStarts')]
    public function test_a_penalised_jump_start_is_itchy_feet(string $offence): void
    {
        $race = $this->penalisedRace($offence);

        $this->assertCount(1, $this->detect('itchy_feet', $race));
    }

    public function test_a_car_out_of_position_is_not_itchy_feet(): void
    {
        $race = $this->penalisedRace('FALSE START - OUT OF POSITION');

        $this->assertCount(0, $this->detect('itchy_feet', $race));
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
    public function test_a_bare_false_start_the_stewards_ruled_out_of_position_is_not_itchy_feet(int $year, int $round, string $driver): void
    {
        $race = $this->penalisedRace('FALSE START', $year, $round, $driver);

        $this->assertCount(0, $this->detect('itchy_feet', $race));
    }

    public function test_a_ruling_names_one_driver_in_one_round(): void
    {
        $this->assertCount(1, $this->detect('itchy_feet', $this->penalisedRace('FALSE START', 2024, 20, 'piastri')));
        $this->assertCount(1, $this->detect('itchy_feet', $this->penalisedRace('FALSE START', 2024, 9, 'perez')));
    }

    public function test_a_ruling_does_not_cover_a_sprint(): void
    {
        $sprint = $this->penalisedRace('FALSE START', 2024, 20, 'perez', SessionType::Sprint);

        $this->assertCount(1, $this->detect('itchy_feet', $sprint));
    }

    private function penalisedRace(string $offence, int $year = 2024, int $round = 5, string $driver = 'someone', ?SessionType $type = null): RaceSession
    {
        $race = $this->raceSession($type ?? SessionType::Race, $this->round($year, $round));
        $this->car($race, 4, driver: Driver::factory()->create(['jolpica_driver_id' => $driver]));
        $this->message($race, "INCIDENT INVOLVING CAR 4 (NOR) NOTED - {$offence}");
        $this->message($race, "FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - {$offence}");

        return $race;
    }
}
