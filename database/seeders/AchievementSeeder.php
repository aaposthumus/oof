<?php

namespace Database\Seeders;

use App\Enums\AchievementSection;
use App\Enums\AchievementShelf;
use App\Enums\AchievementTier;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * The achievements catalogue from docs/achievements.md. Only missing keys are
 * created: a row edited in the CMS keeps its edits.
 */
class AchievementSeeder extends Seeder
{
    private const RACE = ['race'];

    private const RACES = ['race', 'sprint'];

    private const COMPETITIVE = ['race', 'sprint', 'qualifying', 'sprint_qualifying'];

    private const QUALIFYING = ['qualifying', 'sprint_qualifying'];

    private const PRACTICE = ['practice_1', 'practice_2', 'practice_3'];

    private const ALL = ['race', 'sprint', 'qualifying', 'sprint_qualifying', 'practice_1', 'practice_2', 'practice_3'];

    private const PENALTIES = ['high_five', 'perfect_ten', 'drive_thru', 'red_light_green_light'];

    private const TRACK_LIMITS = ['outside_the_lines', 'teachers_stare'];

    /**
     * Never awarded on a sensitive weekend: retirements, the DNS, causes,
     * incidents, collisions, unsafe releases, erratic driving, ignoring flags,
     * offences under the safety car, the Safety Car and Nothing to See Here,
     * and the manual Built Different (2027 pack).
     *
     * @var list<string>
     */
    public const WITHHELD_ON_SENSITIVE_WEEKENDS = [
        'early_lunch', 'home_before_dinner', 'forgot_the_keys',
        'called_the_tow_truck', 'whole_class_detention',
        'it_was_like_that_when_i_got_there', 'bumper_cars',
        'released_into_the_wild', 'failed_the_driving_test', 'selective_vision', 'queue_jumper',
        'took_the_lead', 'second_helping', 'frequent_flyer', 'virtually_there',
        'nothing_to_see_here', 'built_different',
    ];

    /**
     * A penalty absorbs the incident note and, for track limits, the deletions and flag before it.
     */
    private const PENALTY_SUPERSEDES = ['it_was_like_that_when_i_got_there', ...self::TRACK_LIMITS];

    /**
     * Offences absorb the incident note and their own penalty.
     */
    private const OFFENCE_SUPERSEDES = ['it_was_like_that_when_i_got_there', ...self::PENALTIES];

    /**
     * Rows of key, name, section, tier, session types, trigger, and optionally supersedes and stacks.
     *
     * @var list<array{0: string, 1: string, 2: AchievementSection, 3: AchievementTier|null, 4: list<string>, 5: string, 6?: list<string>, 7?: bool}>
     */
    private const CATALOGUE = [
        // Race
        ['lapped_dog', 'Lapped Dog', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Lapped by the leader.'],
        ['good_boy', 'Good Boy', AchievementSection::Race, AchievementTier::Silver, self::RACES, 'Lapped twice or more.', ['lapped_dog']],
        ['blue_carpet', 'Blue Carpet', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Shown 8 or more blue flags in one race.'],
        ['table_for_one', 'Table for One', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Pit stop longer than 5 seconds stationary.'],
        ['fine_dining', 'Fine Dining', AchievementSection::Race, AchievementTier::Bronze, self::RACE, 'Slowest pit stop of the weekend.', ['table_for_one']],
        ['meatball_sub', 'Meatball Sub', AchievementSection::Race, AchievementTier::Platinum, self::RACES, 'Black flag with orange circle ("meatball flag").', [], true],
        ['outside_the_lines', 'Outside the Lines', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Lap time deleted for track limits.'],
        ['teachers_stare', "Teacher's Stare", AchievementSection::Race, AchievementTier::Silver, self::COMPETITIVE, 'Black-and-white warning flag in a race or qualifying, for any reason (track limits, driving slowly, etc.).', ['outside_the_lines']],
        ['it_was_like_that_when_i_got_there', 'It Was Like That When I Got There', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Involved in an incident noted by race control (never implies blame).'],
        ['called_the_tow_truck', 'Called the Tow Truck', AchievementSection::Race, AchievementTier::Silver, self::RACES, 'Caused a safety car.'],
        ['forgot_the_keys', 'Forgot the Keys', AchievementSection::Race, AchievementTier::Gold, self::RACES, 'Did not start (DNS).'],
        ['early_lunch', 'Early Lunch', AchievementSection::Race, AchievementTier::Silver, self::RACES, 'Retired on lap 1.', ['home_before_dinner']],
        ['home_before_dinner', 'Home Before Dinner', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Retired later in the race (DNF).'],
        ['sent_to_your_room', 'Sent to Your Room', AchievementSection::Race, AchievementTier::Gold, self::RACES, 'Disqualified after the race (DSQ).'],
        ['expelled', 'Expelled', AchievementSection::Race, AchievementTier::Platinum, self::RACES, 'Black flag: thrown out during the race.', ['sent_to_your_room']],
        ['reverse_gear', 'Reverse Gear', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Most places lost compared with the grid.'],
        ['all_dressed_up_nowhere_to_go', 'All Dressed Up, Nowhere to Go', AchievementSection::Race, AchievementTier::Gold, self::RACES, 'Fastest lap, but finished outside the points.'],
        ['turned_off_the_lights', 'Turned Off the Lights', AchievementSection::Race, AchievementTier::Bronze, self::RACES, 'Last classified finisher.'],
        ['nothing_to_see_here', 'Nothing to See Here', AchievementSection::Race, AchievementTier::Bronze, self::RACE, 'Unlocked nothing all weekend ("The quiet ones").'],
        // Offences, with a penalty
        ['bumper_cars', 'Bumper Cars', AchievementSection::Offence, AchievementTier::Silver, self::COMPETITIVE, 'Penalty for causing a collision.', self::OFFENCE_SUPERSEDES],
        ['early_bird', 'Early Bird', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'False start, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['selective_hearing', 'Selective Hearing', AchievementSection::Offence, AchievementTier::Platinum, self::COMPETITIVE, "Ignoring the race director's instructions, with a penalty.", self::OFFENCE_SUPERSEDES],
        ['shortcut_tax', 'Shortcut Tax', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Leaving the track and gaining an advantage, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['line_dancing', 'Line Dancing', AchievementSection::Offence, AchievementTier::Platinum, self::COMPETITIVE, 'Crossing the pit entry or exit line, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['smile_for_the_camera', 'Smile for the Camera', AchievementSection::Offence, AchievementTier::Silver, self::COMPETITIVE, 'Speeding in the pit lane, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['extra_wide', 'Extra Wide', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Forcing another driver off the track, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['forgot_to_indicate', 'Forgot to Indicate', AchievementSection::Offence, AchievementTier::Platinum, self::COMPETITIVE, 'Moving under braking, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['selective_vision', 'Selective Vision', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Ignoring a flag (yellow, blue, red), with a penalty.', self::OFFENCE_SUPERSEDES],
        ['queue_jumper', 'Queue Jumper', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Overtaking or going too fast under a safety car or VSC, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['sunday_driver', 'Sunday Driver', AchievementSection::Offence, AchievementTier::Silver, self::COMPETITIVE, 'Maximum delta time noted by race control (too slow).', self::OFFENCE_SUPERSEDES],
        ['failed_the_driving_test', 'Failed the Driving Test', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Erratic or dangerous driving, with a penalty.', self::OFFENCE_SUPERSEDES],
        ['released_into_the_wild', 'Released Into the Wild', AchievementSection::Offence, AchievementTier::Gold, self::COMPETITIVE, 'Unsafe release, with a penalty.', self::OFFENCE_SUPERSEDES],
        // Any session
        ['whole_class_detention', 'Whole Class Detention', AchievementSection::AnySession, AchievementTier::Silver, self::ALL, 'Caused a red flag.'],
        // Penalties
        ['high_five', 'High Five', AchievementSection::Penalty, AchievementTier::Silver, self::RACES, '5-second time penalty.', self::PENALTY_SUPERSEDES, true],
        ['perfect_ten', 'Perfect Ten', AchievementSection::Penalty, AchievementTier::Gold, self::RACES, '10-second time penalty.', self::PENALTY_SUPERSEDES, true],
        ['drive_thru', 'Drive-Thru', AchievementSection::Penalty, AchievementTier::Platinum, self::RACES, 'Drive-through penalty.', self::PENALTY_SUPERSEDES, true],
        ['red_light_green_light', 'Red Light, Green Light', AchievementSection::Penalty, AchievementTier::Platinum, self::RACES, 'Stop-go penalty.', self::PENALTY_SUPERSEDES, true],
        // Qualifying
        ['mirror_match', 'Mirror Match', AchievementSection::Qualifying, AchievementTier::Gold, self::QUALIFYING, 'First car out, and the last car through is your teammate.'],
        ['by_a_whisker', 'By a Whisker', AchievementSection::Qualifying, AchievementTier::Silver, self::QUALIFYING, 'Knocked out by less than 0.015 seconds.'],
        ['deleted_scene', 'Deleted Scene', AchievementSection::Qualifying, AchievementTier::Gold, self::QUALIFYING, 'Best Q3 lap deleted.'],
        ['holding_the_door', 'Holding the Door', AchievementSection::Qualifying, AchievementTier::Bronze, self::QUALIFYING, 'Slowest in Q1.'],
        // Practice
        ['overtime', 'Overtime', AchievementSection::Practice, AchievementTier::Bronze, self::PRACTICE, 'Most laps driven.'],
        ['practice_makes_imperfect', 'Practice Makes Imperfect', AchievementSection::Practice, AchievementTier::Bronze, self::PRACTICE, 'Most deleted laps.'],
        // Glory
        ['did_the_thing', 'Did the Thing', AchievementSection::Glory, AchievementTier::Gold, self::RACES, 'Win.'],
        ['silver_lining', 'Silver Lining', AchievementSection::Glory, AchievementTier::Silver, self::RACES, 'Second place.'],
        ['solid_bronze', 'Solid Bronze', AchievementSection::Glory, AchievementTier::Bronze, self::RACES, 'Third place.'],
        ['plot_twist', 'Plot Twist', AchievementSection::Glory, AchievementTier::Platinum, ['qualifying'], 'Surprise pole.'],
        ['giant_slayer', 'Giant Slayer', AchievementSection::Glory, AchievementTier::Platinum, self::RACE, 'Surprise win.', ['did_the_thing']],
        ['took_the_scenic_route', 'Took the Scenic Route', AchievementSection::Glory, AchievementTier::Platinum, self::RACE, 'Win from P10 or lower on the grid.', ['did_the_thing']],
        ['clean_sweep', 'Clean Sweep', AchievementSection::Glory, AchievementTier::Platinum, self::RACE, 'Grand Chelem: pole, win, fastest lap and led every lap.', ['did_the_thing']],
        ['class_dismissed', 'Class Dismissed', AchievementSection::Glory, AchievementTier::Platinum, self::RACES, 'Championship clinched.'],
        ['to_the_rescue', 'To the Rescue', AchievementSection::Glory, AchievementTier::Gold, self::RACE, 'Called up to race as a replacement driver.'],
        // Safety Car
        ['took_the_lead', 'Took the Lead', AchievementSection::SafetyCar, null, self::RACES, 'The safety car ran 8 or more laps in one race.'],
        ['second_helping', 'Second Helping', AchievementSection::SafetyCar, null, self::RACES, '2 or more safety car deployments in one race.'],
        ['frequent_flyer', 'Frequent Flyer', AchievementSection::SafetyCar, null, self::RACE, 'Season count of deployments (end-of-season award).'],
        ['virtually_there', 'Virtually There', AchievementSection::SafetyCar, null, self::RACES, 'A VSC deployment.', [], true],
    ];

    public function run(): void
    {
        foreach (self::CATALOGUE as $row) {
            [$key, $name, $section, $tier, $sessionTypes, $description] = $row;
            $shelf = match ($section) {
                AchievementSection::Glory => AchievementShelf::Glory,
                AchievementSection::SafetyCar => AchievementShelf::SafetyCar,
                default => AchievementShelf::Oof,
            };

            Achievement::firstOrCreate(['key' => $key], [
                'name' => $name,
                'section' => $section,
                'shelf' => $shelf,
                'tier' => $tier,
                'session_types' => $sessionTypes,
                'stacks' => $row[7] ?? false,
                'supersedes' => $row[6] ?? [],
                'description' => $description,
                'is_enabled' => true,
                'withheld_on_sensitive_weekends' => in_array($key, self::WITHHELD_ON_SENSITIVE_WEEKENDS, true),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::CATALOGUE, 0);
    }
}
