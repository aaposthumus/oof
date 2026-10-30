<?php

namespace Tests\Feature\Achievements\Detectors;

use App\Achievements\Detection;
use App\Achievements\Detector;
use App\Achievements\Detectors\BumperCarsDetector;
use App\Achievements\Detectors\CalledTheTowTruckDetector;
use App\Achievements\Detectors\ColouringOutsideTheLinesDetector;
use App\Achievements\Detectors\DidntSeeTheSignDetector;
use App\Achievements\Detectors\DriveThruDetector;
use App\Achievements\Detectors\EarlyBirdDetector;
use App\Achievements\Detectors\ExpelledDetector;
use App\Achievements\Detectors\FailedTheDrivingTestDetector;
use App\Achievements\Detectors\ForgotToIndicateDetector;
use App\Achievements\Detectors\HighFiveDetector;
use App\Achievements\Detectors\ItWasLikeThatDetector;
use App\Achievements\Detectors\LaneDisciplineDetector;
use App\Achievements\Detectors\MeatballSubDetector;
use App\Achievements\Detectors\MirrorSignalManoeuvreDetector;
use App\Achievements\Detectors\NoteSentHomeDetector;
use App\Achievements\Detectors\PerfectTenDetector;
use App\Achievements\Detectors\QueueJumperDetector;
use App\Achievements\Detectors\RecalculatingDetector;
use App\Achievements\Detectors\RedLightGreenLightDetector;
use App\Achievements\Detectors\ReleasedIntoTheWildDetector;
use App\Achievements\Detectors\RoadHogDetector;
use App\Achievements\Detectors\SelectiveHearingDetector;
use App\Achievements\Detectors\SmileForTheCameraDetector;
use App\Achievements\Detectors\SundayDriverDetector;
use App\Achievements\Detectors\WholeClassDetentionDetector;
use App\Enums\AwardConfidence;
use App\Enums\AwardReviewReason;
use App\Enums\SessionType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSessions;
use Tests\TestCase;

class RaceControlDetectorsTest extends TestCase
{
    use BuildsSessions, LazilyRefreshDatabase;

    /**
     * An offence achievement per detector, with a real wording of its offence.
     *
     * @return array<string, array{class-string<Detector>, string}>
     */
    public static function offences(): array
    {
        return [
            'bumper cars' => [BumperCarsDetector::class, 'CAUSING A COLLISION'],
            'early bird' => [EarlyBirdDetector::class, 'FALSE START - MOVING BEFORE SIGNAL'],
            'selective hearing' => [SelectiveHearingDetector::class, 'FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS'],
            'recalculating' => [RecalculatingDetector::class, 'LEAVING THE TRACK AND GAINING AN ADVANTAGE'],
            'lane discipline' => [LaneDisciplineDetector::class, 'CROSSING THE LINE AT PIT ENTRY'],
            'smile for the camera' => [SmileForTheCameraDetector::class, 'SPEEDING IN THE PIT LANE'],
            'road hog' => [RoadHogDetector::class, 'FORCING ANOTHER DRIVER OFF THE TRACK'],
            'forgot to indicate' => [ForgotToIndicateDetector::class, 'MOVING UNDER BRAKING'],
            'didnt see the sign' => [DidntSeeTheSignDetector::class, 'FAILING TO SLOW FOR YELLOW FLAGS'],
            'queue jumper' => [QueueJumperDetector::class, 'OVERTAKING UNDER SAFETY CAR'],
            'failed the driving test' => [FailedTheDrivingTestDetector::class, 'DRIVING ERRATICALLY'],
            'released into the wild' => [ReleasedIntoTheWildDetector::class, 'UNSAFE RELEASE'],
        ];
    }

    /**
     * @param  class-string<Detector>  $detector
     */
    #[DataProvider('offences')]
    public function test_an_offence_counts_once_it_is_penalised(string $detector, string $offence): void
    {
        $race = $this->raceSession();
        $car = $this->car($race, 4);
        $this->message($race, "INCIDENT INVOLVING CAR 4 (NOR) NOTED - {$offence}");
        $this->message($race, "FIA STEWARDS: INCIDENT INVOLVING CAR 4 (NOR) UNDER INVESTIGATION - {$offence}");
        $this->assertCount(0, $this->detect($detector, $race));

        $this->message($race, "FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - {$offence}");
        $detection = $this->detect($detector, $race)->sole();

        $this->assertSame($car->id, $detection->driverEntryId);
        $this->assertSame(1, $detection->carsNamed);
        $this->assertCount(3, $detection->evidence['messages']);
    }

    public function test_a_reprimand_or_warning_is_not_a_penalty(): void
    {
        $race = $this->raceSession();
        $this->car($race, 4);
        $this->message($race, 'FIA STEWARDS: WARNING FOR CAR 4 (NOR) - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: REPRIMAND (DRIVING) FOR CAR 4 (NOR) - CAUSING A COLLISION');

        $this->assertCount(0, $this->detect(BumperCarsDetector::class, $race));
    }

    public function test_lane_discipline_needs_the_pit_entry_or_exit_line(): void
    {
        $race = $this->raceSession();
        $this->car($race, 4);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - PIT LANE INFRINGEMENT - FAST LANE');

        $this->assertCount(0, $this->detect(LaneDisciplineDetector::class, $race));
    }

    public function test_a_grid_penalty_counts_with_its_size_as_detail(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->car($qualifying, 4);
        $this->message($qualifying, 'FIA STEWARDS: 3 PLACE GRID PENALTY FOR CAR 4 (NOR) - IMPEDING - CAUSING A COLLISION');

        $this->assertSame('3 places', $this->detect(BumperCarsDetector::class, $qualifying)->sole()->detail);
    }

    /**
     * @return array<string, array{class-string<Detector>, string, string}>
     */
    public static function penalties(): array
    {
        return [
            'high five' => [HighFiveDetector::class, '5 SECOND TIME PENALTY', '5 s'],
            'perfect ten' => [PerfectTenDetector::class, '10 SECOND TIME PENALTY', '10 s'],
            'drive-thru' => [DriveThruDetector::class, 'DRIVE THROUGH PENALTY', 'drive-through'],
            'red light, green light' => [RedLightGreenLightDetector::class, '10 SECOND STOP/GO PENALTY', 'stop-go 10 s'],
        ];
    }

    /**
     * @param  class-string<Detector>  $detector
     */
    #[DataProvider('penalties')]
    public function test_a_penalty_counts_per_penalty(string $detector, string $penalty, string $detail): void
    {
        $race = $this->raceSession();
        $this->car($race, 23);
        $this->message($race, "FIA STEWARDS: {$penalty} FOR CAR 23 (ALB) - UNSAFE CONDITION");

        $detection = $this->detect($detector, $race)->sole();

        $this->assertSame($detail, $detection->detail);
        $this->assertStringStartsWith('incident:', $detection->eventKey);
    }

    public function test_a_served_penalty_is_not_a_second_penalty(): void
    {
        $race = $this->raceSession();
        $this->car($race, 63);
        $this->message($race, 'FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE');
        $this->message($race, 'FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:04:59)');

        $this->assertCount(1, $this->detect(HighFiveDetector::class, $race));
        $this->assertCount(1, $this->detect(RecalculatingDetector::class, $race));
    }

    public function test_sunday_driver_counts_when_noted_for_one_car(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->car($qualifying, 6);
        $this->car($qualifying, 77);
        $this->message($qualifying, 'Q1 INCIDENT INVOLVING CAR 6 (HAD) NOTED - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS - MAXIMUM DELTA TIME');
        $this->message($qualifying, 'Q1 INCIDENT INVOLVING CARS 6 (HAD), 77 (BOT) NOTED - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS - MAXIMUM DELTA TIME');

        $detection = $this->detect(SundayDriverDetector::class, $qualifying)->sole();

        $this->assertSame(6, $this->data($qualifying)->entry($detection->driverEntryId)->car_number);
        $this->assertSame(AwardConfidence::Medium, $detection->confidence);
    }

    public function test_involvement_reaches_every_car_named(): void
    {
        $race = $this->raceSession();
        $this->car($race, 87);
        $this->car($race, 10);
        $this->message($race, 'TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) NOTED - CAUSING A COLLISION');
        $this->message($race, 'FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) UNDER INVESTIGATION - CAUSING A COLLISION');

        $detections = $this->detect(ItWasLikeThatDetector::class, $race);

        $this->assertCount(2, $detections);
        $this->assertSame([true], $detections->pluck('isOpen')->unique()->all());
        $this->assertSame([AwardConfidence::Medium], $detections->pluck('confidence')->unique()->all());
        $this->assertCount(1, $detections->pluck('eventKey')->unique());
    }

    public function test_flags_shown_to_one_car(): void
    {
        $race = $this->raceSession();
        $this->car($race, 27);
        $this->car($race, 18);
        $this->message($race, 'BLACK AND ORANGE FLAG FOR CAR 27 (HUL)', ['flag' => 'BLACK AND ORANGE']);
        $this->message($race, 'BLACK FLAG FOR CAR 27 (HUL)', ['flag' => 'BLACK']);
        $this->message($race, 'BLACK AND WHITE FLAG FOR CAR 18 (STR) - TRACK LIMITS', ['flag' => 'BLACK AND WHITE']);
        $this->message($race, 'BLACK AND WHITE FLAG FOR CAR 27 (HUL) - MOVING UNDER BRAKING', ['flag' => 'BLACK AND WHITE']);

        $this->assertCount(1, $this->detect(MeatballSubDetector::class, $race));
        $this->assertStringStartsWith('disqualification:', $this->detect(ExpelledDetector::class, $race)->sole()->eventKey);
        $this->assertStringStartsWith('track_limits:', $this->detect(NoteSentHomeDetector::class, $race)->sole()->eventKey);
    }

    public function test_a_re_issued_deletion_counts_once(): void
    {
        $race = $this->raceSession();
        $this->car($race, 14);
        $this->message($race, 'CAR 14 (ALO) TIME 1:31.617 DELETED - TRACK LIMITS AT TURN 13 LAP 3 18:15:32');
        $this->message($race, 'CAR 14 (ALO) TIME 1:31.617 DELETED - TRACK LIMITS AT TURN 13 LAP 3 18:15:32');
        $this->message($race, 'CAR 14 (ALO) TIME 1:32.001 DELETED - TRACK LIMITS AT TURN 4 LAP 9 18:25:00');

        $this->assertCount(2, $this->detect(ColouringOutsideTheLinesDetector::class, $race));
    }

    public function test_blue_flags_count_from_the_threshold(): void
    {
        config(['achievements.blue_flags' => 3]);
        $race = $this->raceSession();
        $this->car($race, 2);
        $this->car($race, 21);

        foreach (range(1, 3) as $flag) {
            $this->message($race, 'WAVED BLUE FLAG FOR CAR 2 (SAR) TIMED AT 15:0'.$flag.':00', ['flag' => 'BLUE']);
        }

        $this->message($race, 'WAVED BLUE FLAG FOR CAR 21 (DEV) TIMED AT 15:10:00', ['flag' => 'BLUE']);

        $detection = $this->detect(MirrorSignalManoeuvreDetector::class, $race)->sole();
        $this->assertSame('3 blue flags', $detection->detail);
    }

    public function test_a_red_flag_is_put_on_the_one_car_named_just_before(): void
    {
        $race = $this->raceSession(SessionType::Practice1);
        $car = $this->car($race, 16);
        $this->message($race, 'CAR 16 (LEC) STOPPED AT TURN 7');
        $this->message($race, 'RED FLAG', ['flag' => 'RED']);

        $detection = $this->detect(WholeClassDetentionDetector::class, $race)->sole();

        $this->assertSame($car->id, $detection->driverEntryId);
        $this->assertSame(AwardConfidence::Low, $detection->confidence);
    }

    public function test_without_one_car_to_blame_the_case_goes_to_review_without_a_car(): void
    {
        config(['achievements.cause_window_seconds' => 300]);
        $race = $this->raceSession();
        $this->car($race, 16);
        $this->car($race, 55);
        $this->message($race, 'CAR 16 (LEC) STOPPED AT TURN 7');
        $this->message($race, 'CAR 55 (SAI) STOPPED AT TURN 8');
        $this->message($race, 'SAFETY CAR DEPLOYED', ['category' => 'SafetyCar']);

        $detection = $this->detect(CalledTheTowTruckDetector::class, $race)->sole();

        $this->assertNull($detection->driverEntryId);
        $this->assertSame(AwardReviewReason::NoClearCause, $detection->reviewReason);
    }

    public function test_a_red_flag_just_after_the_chequered_flag_does_not_count(): void
    {
        $qualifying = $this->raceSession(SessionType::Qualifying);
        $this->message($qualifying, 'CHEQUERED FLAG', ['flag' => 'CHEQUERED']);
        $this->message($qualifying, 'RED FLAG', ['flag' => 'RED', 'issued_at' => now()->setDate(2024, 5, 5)->setTime(20, 1, 30)]);

        $this->assertSame([], $this->detect(WholeClassDetentionDetector::class, $qualifying)->map(fn (Detection $detection) => $detection->instanceKey)->all());
    }
}
