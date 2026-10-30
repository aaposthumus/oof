<?php

namespace App\RaceControl;

/**
 * Offences the stewards name in race control messages, grouped from their
 * many wordings ("UNSAFE RELEASE", "RELEASED IN AN UNSAFE CONDITION").
 */
enum Offence: string
{
    case FalseStart = 'false_start';
    case StartingProcedure = 'starting_procedure';
    case UnsafeRelease = 'unsafe_release';
    case LeavingTheTrack = 'leaving_the_track';
    case TrackLimits = 'track_limits';
    case CausingACollision = 'causing_a_collision';
    case ForcingOffTheTrack = 'forcing_off_the_track';
    case Impeding = 'impeding';
    case MovingUnderBraking = 'moving_under_braking';
    case ErraticDriving = 'erratic_driving';
    case DrivingSlowly = 'driving_slowly';
    case UnsafeRejoin = 'unsafe_rejoin';
    case UnsafeCondition = 'unsafe_condition';
    case SpeedingInThePitLane = 'speeding_in_the_pit_lane';
    case SafetyCarInfringement = 'safety_car_infringement';
    case PitLaneInfringement = 'pit_lane_infringement';
    case YellowFlagInfringement = 'yellow_flag_infringement';
    case RedFlagInfringement = 'red_flag_infringement';
    case IgnoringBlueFlags = 'ignoring_blue_flags';
    case ServingAPenaltyIncorrectly = 'serving_a_penalty_incorrectly';
    case MaximumTime = 'maximum_time';
    case OnFoot = 'on_foot';
    case RaceDirectorsInstructions = 'race_directors_instructions';

    /**
     * Patterns per offence, tried in order, so a specific offence wins over
     * the general "FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS" it may follow.
     *
     * @var array<string, string> Offence value => reason regex
     */
    private const PATTERNS = [
        'false_start' => '/FALSE START|JUMP START/',
        'starting_procedure' => '/STARTING PROCEDURE|STARTING LOCATION|GRID POSITION|RESTART INFRINGEMENT/',
        'unsafe_release' => '/UNSAFE RELEASE|RELEAS(?:ED|ING) IN (?:AN )?UNSAFE CONDITION/',
        'leaving_the_track' => '/LEAVING THE TRACK.* GAINING AN ADVANTAGE/',
        'track_limits' => '/TRACK LIMIT/',
        'causing_a_collision' => '/CAUSING A COLLISION/',
        'forcing_off_the_track' => '/FORCING ANOTHER (?:DRIVER|CAR) OFF THE TRACK/',
        'impeding' => '/IMPEDING/',
        'moving_under_braking' => '/MOVING UNDER BRAKING/',
        'erratic_driving' => '/DRIVING ERRATICALLY|DANGEROUS DRIVING|CHANGE OF DIRECTION|WEAVING/',
        'driving_slowly' => '/DRIVING UNNECESSARILY SLOWLY/',
        'unsafe_rejoin' => '/REJOIN/',
        'unsafe_condition' => '/UNSAFE CONDITION|LOOSE/',
        'speeding_in_the_pit_lane' => '/SPEEDING IN THE PIT LANE/',
        'safety_car_infringement' => '/SAFETY CAR|\bVSC\b/',
        'pit_lane_infringement' => '/PIT LANE INFRINGEMENT|PIT STOP INFRINGEMENT|PIT ENTRY|PIT EXIT|PIT LANE|FAST LANE|BOLLARD|PRACTICE START/',
        'yellow_flag_infringement' => '/YELLOW FLAG/',
        'red_flag_infringement' => '/RED FLAG/',
        'ignoring_blue_flags' => '/BLUE FLAG/',
        'serving_a_penalty_incorrectly' => '/SERV(?:E|ING) (?:TIME )?PENALTY/',
        'maximum_time' => '/MAXIMUM (?:DELTA|LAP) TIME/',
        'on_foot' => '/ON FOOT|CROSSING THE TRACK/',
        'race_directors_instructions' => '/RACE DIRECTORS INSTRUCTIONS/',
    ];

    /**
     * The offence a stewards' reason names, if it is one of these.
     */
    public static function fromReason(?string $reason): ?self
    {
        if ($reason === null) {
            return null;
        }

        foreach (self::PATTERNS as $offence => $pattern) {
            if (preg_match($pattern, $reason) === 1) {
                return self::from($offence);
            }
        }

        return null;
    }

    public function label(): string
    {
        return match ($this) {
            self::FalseStart => 'False start',
            self::StartingProcedure => 'Starting procedure',
            self::UnsafeRelease => 'Unsafe release',
            self::LeavingTheTrack => 'Leaving the track and gaining an advantage',
            self::TrackLimits => 'Track limits',
            self::CausingACollision => 'Causing a collision',
            self::ForcingOffTheTrack => 'Forcing another driver off the track',
            self::Impeding => 'Impeding',
            self::MovingUnderBraking => 'Moving under braking',
            self::ErraticDriving => 'Erratic driving',
            self::DrivingSlowly => 'Driving unnecessarily slowly',
            self::UnsafeRejoin => 'Unsafe rejoin',
            self::UnsafeCondition => 'Unsafe condition',
            self::SpeedingInThePitLane => 'Speeding in the pit lane',
            self::SafetyCarInfringement => 'Safety car or VSC infringement',
            self::PitLaneInfringement => 'Pit lane infringement',
            self::YellowFlagInfringement => 'Yellow flag infringement',
            self::RedFlagInfringement => 'Red flag infringement',
            self::IgnoringBlueFlags => 'Ignoring blue flags',
            self::ServingAPenaltyIncorrectly => 'Serving a penalty incorrectly',
            self::MaximumTime => 'Maximum delta or lap time',
            self::OnFoot => 'On foot on the track',
            self::RaceDirectorsInstructions => 'Race director\'s instructions',
        };
    }
}
