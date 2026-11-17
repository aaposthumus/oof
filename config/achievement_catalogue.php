<?php

use App\Achievements\Detectors\AllDressedUpDetector;
use App\Achievements\Detectors\BlueCarpetDetector;
use App\Achievements\Detectors\BumperCarsDetector;
use App\Achievements\Detectors\ByAWhiskerDetector;
use App\Achievements\Detectors\CalledTheTowTruckDetector;
use App\Achievements\Detectors\ClassDismissedDetector;
use App\Achievements\Detectors\CleanSweepDetector;
use App\Achievements\Detectors\DeletedSceneDetector;
use App\Achievements\Detectors\DidTheThingDetector;
use App\Achievements\Detectors\DriveThruDetector;
use App\Achievements\Detectors\EarlyBirdDetector;
use App\Achievements\Detectors\EarlyLunchDetector;
use App\Achievements\Detectors\ExpelledDetector;
use App\Achievements\Detectors\ExtraWideDetector;
use App\Achievements\Detectors\FailedTheDrivingTestDetector;
use App\Achievements\Detectors\FineDiningDetector;
use App\Achievements\Detectors\ForgotTheKeysDetector;
use App\Achievements\Detectors\ForgotToIndicateDetector;
use App\Achievements\Detectors\FrequentFlyerDetector;
use App\Achievements\Detectors\GiantSlayerDetector;
use App\Achievements\Detectors\GoodBoyDetector;
use App\Achievements\Detectors\HighFiveDetector;
use App\Achievements\Detectors\HoldingTheDoorDetector;
use App\Achievements\Detectors\HomeBeforeDinnerDetector;
use App\Achievements\Detectors\ItWasLikeThatDetector;
use App\Achievements\Detectors\LappedDogDetector;
use App\Achievements\Detectors\LineDancingDetector;
use App\Achievements\Detectors\MeatballSubDetector;
use App\Achievements\Detectors\MirrorMatchDetector;
use App\Achievements\Detectors\NothingToSeeHereDetector;
use App\Achievements\Detectors\OutsideTheLinesDetector;
use App\Achievements\Detectors\OvertimeDetector;
use App\Achievements\Detectors\PerfectTenDetector;
use App\Achievements\Detectors\PlotTwistDetector;
use App\Achievements\Detectors\PracticeMakesImperfectDetector;
use App\Achievements\Detectors\QueueJumperDetector;
use App\Achievements\Detectors\RedLightGreenLightDetector;
use App\Achievements\Detectors\ReleasedIntoTheWildDetector;
use App\Achievements\Detectors\ReverseGearDetector;
use App\Achievements\Detectors\SecondHelpingDetector;
use App\Achievements\Detectors\SelectiveHearingDetector;
use App\Achievements\Detectors\SelectiveVisionDetector;
use App\Achievements\Detectors\SentToYourRoomDetector;
use App\Achievements\Detectors\ShortcutTaxDetector;
use App\Achievements\Detectors\SilverLiningDetector;
use App\Achievements\Detectors\SmileForTheCameraDetector;
use App\Achievements\Detectors\SolidBronzeDetector;
use App\Achievements\Detectors\SundayDriverDetector;
use App\Achievements\Detectors\TableForOneDetector;
use App\Achievements\Detectors\TeachersStareDetector;
use App\Achievements\Detectors\TookTheLeadDetector;
use App\Achievements\Detectors\TookTheScenicRouteDetector;
use App\Achievements\Detectors\ToTheRescueDetector;
use App\Achievements\Detectors\TurnedOffTheLightsDetector;
use App\Achievements\Detectors\VirtuallyThereDetector;
use App\Achievements\Detectors\WholeClassDetentionDetector;

/*
|--------------------------------------------------------------------------
| Achievement catalogue
|--------------------------------------------------------------------------
|
| One entry per achievement, in the order the engine runs them. Data only,
| so the file is safe for config caching.
|
| Editorial defaults (name, section, shelf, tier, score_category,
| description, supersedes) seed a new row once; the CMS owns them after
| that. Mechanics (sessions, stacks, recipient, scope,
| withheld_on_sensitive_weekends, trigger) belong to this file:
| oof:achievements:sync writes them to the row, and the CMS shows them
| read-only.
|
| A trigger is one of a closed set of types: match, count, extreme or
| custom (a class, for logic that does not fit). See App\Achievements\Triggers.
|
*/

return [

    'achievements' => [

        'bumper_cars' => [
            'name' => 'Bumper Cars',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Penalty for causing a collision.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => BumperCarsDetector::class],
        ],

        'early_bird' => [
            'name' => 'Early Bird',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'False start, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => EarlyBirdDetector::class],
        ],

        'selective_hearing' => [
            'name' => 'Selective Hearing',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Ignoring the race director\'s instructions, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SelectiveHearingDetector::class],
        ],

        'shortcut_tax' => [
            'name' => 'Shortcut Tax',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Leaving the track and gaining an advantage, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ShortcutTaxDetector::class],
        ],

        'line_dancing' => [
            'name' => 'Line Dancing',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Crossing the pit entry or exit line, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => LineDancingDetector::class],
        ],

        'smile_for_the_camera' => [
            'name' => 'Smile for the Camera',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Speeding in the pit lane, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SmileForTheCameraDetector::class],
        ],

        'extra_wide' => [
            'name' => 'Extra Wide',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Forcing another driver off the track, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ExtraWideDetector::class],
        ],

        'forgot_to_indicate' => [
            'name' => 'Forgot to Indicate',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Moving under braking, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ForgotToIndicateDetector::class],
        ],

        'selective_vision' => [
            'name' => 'Selective Vision',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Ignoring a flag (yellow, blue, red), with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => SelectiveVisionDetector::class],
        ],

        'queue_jumper' => [
            'name' => 'Queue Jumper',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Overtaking or going too fast under a safety car or VSC, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => QueueJumperDetector::class],
        ],

        'failed_the_driving_test' => [
            'name' => 'Failed the Driving Test',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Erratic or dangerous driving, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => FailedTheDrivingTestDetector::class],
        ],

        'released_into_the_wild' => [
            'name' => 'Released Into the Wild',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'reliability',
            'description' => 'Unsafe release, with a penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => ReleasedIntoTheWildDetector::class],
        ],

        'sunday_driver' => [
            'name' => 'Sunday Driver',
            'section' => 'offence',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Maximum delta time noted by race control (too slow).',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SundayDriverDetector::class],
        ],

        'high_five' => [
            'name' => 'High Five',
            'section' => 'penalty',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => '5-second time penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'outside_the_lines', 'teachers_stare'],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => HighFiveDetector::class],
        ],

        'perfect_ten' => [
            'name' => 'Perfect Ten',
            'section' => 'penalty',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => '10-second time penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'outside_the_lines', 'teachers_stare'],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => PerfectTenDetector::class],
        ],

        'drive_thru' => [
            'name' => 'Drive-Thru',
            'section' => 'penalty',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Drive-through penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'outside_the_lines', 'teachers_stare'],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => DriveThruDetector::class],
        ],

        'red_light_green_light' => [
            'name' => 'Red Light, Green Light',
            'section' => 'penalty',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Stop-go penalty.',
            'supersedes' => ['it_was_like_that_when_i_got_there', 'outside_the_lines', 'teachers_stare'],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => RedLightGreenLightDetector::class],
        ],

        'it_was_like_that_when_i_got_there' => [
            'name' => 'It Was Like That When I Got There',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'driver',
            'description' => 'Involved in an incident noted by race control (never implies blame).',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => ItWasLikeThatDetector::class],
        ],

        'meatball_sub' => [
            'name' => 'Meatball Sub',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'reliability',
            'description' => 'Black flag with orange circle ("meatball flag").',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => MeatballSubDetector::class],
        ],

        'expelled' => [
            'name' => 'Expelled',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Black flag: thrown out during the race.',
            'supersedes' => ['sent_to_your_room'],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ExpelledDetector::class],
        ],

        'outside_the_lines' => [
            'name' => 'Outside the Lines',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'driver',
            'description' => 'Lap time deleted for track limits.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => OutsideTheLinesDetector::class],
        ],

        'teachers_stare' => [
            'name' => 'Teacher\'s Stare',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Black-and-white warning flag in a race or qualifying, for any reason (track limits, driving slowly, etc.).',
            'supersedes' => ['outside_the_lines'],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => TeachersStareDetector::class],
        ],

        'blue_carpet' => [
            'name' => 'Blue Carpet',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Shown 8 or more blue flags in one race.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => BlueCarpetDetector::class],
        ],

        'whole_class_detention' => [
            'name' => 'Whole Class Detention',
            'section' => 'any',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Caused a red flag.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint', 'qualifying', 'sprint_qualifying', 'practice_1', 'practice_2', 'practice_3'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => WholeClassDetentionDetector::class],
        ],

        'called_the_tow_truck' => [
            'name' => 'Called the Tow Truck',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Caused a safety car.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => CalledTheTowTruckDetector::class],
        ],

        'lapped_dog' => [
            'name' => 'Lapped Dog',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Lapped by the leader.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => LappedDogDetector::class],
        ],

        'good_boy' => [
            'name' => 'Good Boy',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'pace',
            'description' => 'Lapped twice or more.',
            'supersedes' => ['lapped_dog'],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => GoodBoyDetector::class],
        ],

        'forgot_the_keys' => [
            'name' => 'Forgot the Keys',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'reliability',
            'description' => 'Did not start (DNS).',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => ForgotTheKeysDetector::class],
        ],

        'early_lunch' => [
            'name' => 'Early Lunch',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'reliability',
            'description' => 'Retired on lap 1.',
            'supersedes' => ['home_before_dinner'],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => EarlyLunchDetector::class],
        ],

        'home_before_dinner' => [
            'name' => 'Home Before Dinner',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'reliability',
            'description' => 'Retired later in the race (DNF).',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => HomeBeforeDinnerDetector::class],
        ],

        'sent_to_your_room' => [
            'name' => 'Sent to Your Room',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'reliability',
            'description' => 'Disqualified after the race (DSQ).',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SentToYourRoomDetector::class],
        ],

        'reverse_gear' => [
            'name' => 'Reverse Gear',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Most places lost compared with the grid.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ReverseGearDetector::class],
        ],

        'all_dressed_up_nowhere_to_go' => [
            'name' => 'All Dressed Up, Nowhere to Go',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'pace',
            'description' => 'Fastest lap, but finished outside the points.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => AllDressedUpDetector::class],
        ],

        'turned_off_the_lights' => [
            'name' => 'Turned Off the Lights',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Last classified finisher.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => TurnedOffTheLightsDetector::class],
        ],

        'table_for_one' => [
            'name' => 'Table for One',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'reliability',
            'description' => 'Pit stop longer than 5 seconds stationary.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => TableForOneDetector::class],
        ],

        'fine_dining' => [
            'name' => 'Fine Dining',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'reliability',
            'description' => 'Slowest pit stop of the weekend.',
            'supersedes' => ['table_for_one'],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => FineDiningDetector::class],
        ],

        'mirror_match' => [
            'name' => 'Mirror Match',
            'section' => 'qualifying',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'pace',
            'description' => 'First car out, and the last car through is your teammate.',
            'supersedes' => [],
            'sessions' => ['qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => MirrorMatchDetector::class],
        ],

        'by_a_whisker' => [
            'name' => 'By a Whisker',
            'section' => 'qualifying',
            'shelf' => 'oof',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Knocked out by less than 0.015 seconds.',
            'supersedes' => [],
            'sessions' => ['qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ByAWhiskerDetector::class],
        ],

        'deleted_scene' => [
            'name' => 'Deleted Scene',
            'section' => 'qualifying',
            'shelf' => 'oof',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Best Q3 lap deleted.',
            'supersedes' => [],
            'sessions' => ['qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => DeletedSceneDetector::class],
        ],

        'holding_the_door' => [
            'name' => 'Holding the Door',
            'section' => 'qualifying',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Slowest in Q1.',
            'supersedes' => [],
            'sessions' => ['qualifying', 'sprint_qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => HoldingTheDoorDetector::class],
        ],

        'overtime' => [
            'name' => 'Overtime',
            'section' => 'practice',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'pace',
            'description' => 'Most laps driven.',
            'supersedes' => [],
            'sessions' => ['practice_1', 'practice_2', 'practice_3'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => OvertimeDetector::class],
        ],

        'practice_makes_imperfect' => [
            'name' => 'Practice Makes Imperfect',
            'section' => 'practice',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'driver',
            'description' => 'Most deleted laps.',
            'supersedes' => [],
            'sessions' => ['practice_1', 'practice_2', 'practice_3'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => PracticeMakesImperfectDetector::class],
        ],

        'did_the_thing' => [
            'name' => 'Did the Thing',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Win.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => DidTheThingDetector::class],
        ],

        'silver_lining' => [
            'name' => 'Silver Lining',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'silver',
            'score_category' => 'driver',
            'description' => 'Second place.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SilverLiningDetector::class],
        ],

        'solid_bronze' => [
            'name' => 'Solid Bronze',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'bronze',
            'score_category' => 'driver',
            'description' => 'Third place.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => SolidBronzeDetector::class],
        ],

        'plot_twist' => [
            'name' => 'Plot Twist',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Surprise pole.',
            'supersedes' => [],
            'sessions' => ['qualifying'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => PlotTwistDetector::class],
        ],

        'giant_slayer' => [
            'name' => 'Giant Slayer',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Surprise win.',
            'supersedes' => ['did_the_thing'],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => GiantSlayerDetector::class],
        ],

        'took_the_scenic_route' => [
            'name' => 'Took the Scenic Route',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Win from P10 or lower on the grid.',
            'supersedes' => ['did_the_thing'],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => TookTheScenicRouteDetector::class],
        ],

        'clean_sweep' => [
            'name' => 'Clean Sweep',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Grand Chelem: pole, win, fastest lap and led every lap.',
            'supersedes' => ['did_the_thing'],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => CleanSweepDetector::class],
        ],

        'class_dismissed' => [
            'name' => 'Class Dismissed',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'platinum',
            'score_category' => 'driver',
            'description' => 'Championship clinched.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ClassDismissedDetector::class],
        ],

        'to_the_rescue' => [
            'name' => 'To the Rescue',
            'section' => 'glory',
            'shelf' => 'glory',
            'tier' => 'gold',
            'score_category' => 'driver',
            'description' => 'Called up to race as a replacement driver.',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => false,
            'trigger' => ['type' => 'custom', 'class' => ToTheRescueDetector::class],
        ],

        'took_the_lead' => [
            'name' => 'Took the Lead',
            'section' => 'safety_car',
            'shelf' => 'safety_car',
            'tier' => null,
            'score_category' => 'driver',
            'description' => 'The safety car ran 8 or more laps in one race.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'safety_car',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => TookTheLeadDetector::class],
        ],

        'second_helping' => [
            'name' => 'Second Helping',
            'section' => 'safety_car',
            'shelf' => 'safety_car',
            'tier' => null,
            'score_category' => 'driver',
            'description' => '2 or more safety car deployments in one race.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => false,
            'recipient' => 'safety_car',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => SecondHelpingDetector::class],
        ],

        'frequent_flyer' => [
            'name' => 'Frequent Flyer',
            'section' => 'safety_car',
            'shelf' => 'safety_car',
            'tier' => null,
            'score_category' => 'driver',
            'description' => 'Season count of deployments (end-of-season award).',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'safety_car',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => FrequentFlyerDetector::class],
        ],

        'virtually_there' => [
            'name' => 'Virtually There',
            'section' => 'safety_car',
            'shelf' => 'safety_car',
            'tier' => null,
            'score_category' => 'driver',
            'description' => 'A VSC deployment.',
            'supersedes' => [],
            'sessions' => ['race', 'sprint'],
            'stacks' => true,
            'recipient' => 'safety_car',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => VirtuallyThereDetector::class],
        ],

        'nothing_to_see_here' => [
            'name' => 'Nothing to See Here',
            'section' => 'race',
            'shelf' => 'oof',
            'tier' => 'bronze',
            'score_category' => 'driver',
            'description' => 'Unlocked nothing all weekend ("The quiet ones").',
            'supersedes' => [],
            'sessions' => ['race'],
            'stacks' => false,
            'recipient' => 'driver',
            'scope' => 'session',
            'withheld_on_sensitive_weekends' => true,
            'trigger' => ['type' => 'custom', 'class' => NothingToSeeHereDetector::class],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Planned
    |--------------------------------------------------------------------------
    |
    | Settings already decided for achievements of a later pack, applied when
    | their entry is added: Built Different is never awarded on a sensitive
    | weekend, and the surprise achievements count ×1 in the OOFscore.
    |
    */

    'planned' => [
        'built_different' => ['withheld_on_sensitive_weekends' => true],
        'economy_class' => ['score_category' => 'surprise'],
        'peaked_too_soon' => ['score_category' => 'surprise'],
        'led_astray' => ['score_category' => 'surprise'],
    ],

];
