<?php

namespace App\Excavation\Support;

use App\Models\MomentCandidate;
use Illuminate\Support\Str;

/**
 * Turns a candidate's type and raw evidence into text a reviewer can read.
 */
final class EvidenceSummary
{
    private const TYPE_LABELS = [
        'fewest_starters' => 'Fewest starters',
        'fewest_finishers' => 'Fewest finishers',
        'grid_to_win_climb' => 'Grid to win climb',
        'surprise_winner' => 'Surprise winner',
        'first_time_winner' => 'First-time winner',
        'win_drought_ended' => 'Win drought ended',
        'podium_drought_ended' => 'Podium drought ended',
        'most_retirements' => 'Most retirements',
        'curious_retirement' => 'Curious retirement',
        'disqualification' => 'Disqualification',
        'shortest_race' => 'Shortest race',
        'dominant_win' => 'Dominant win',
        'closest_finish' => 'Closest finish',
        'title_decider' => 'Title decider',
        'age_record' => 'Age record',
        'start_milestone' => 'Start milestone',
        'slowest_pit_stop' => 'Slowest pit stop',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return self::TYPE_LABELS;
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? Str::headline($type);
    }

    /**
     * A short line for tables, e.g. "6 of 20 started".
     */
    public static function summary(MomentCandidate $candidate): string
    {
        $evidence = $candidate->evidence;

        return match ($candidate->type) {
            'fewest_starters' => "{$evidence['starters']} of {$evidence['entries']} started",
            'fewest_finishers' => "{$evidence['running']} of {$evidence['starters']} running at the flag",
            'grid_to_win_climb' => "Won from {$evidence['grid']}".(($evidence['pit_lane_start'] ?? false) ? ' (pit lane)' : '').", {$evidence['places_gained']} places gained",
            'surprise_winner' => self::surpriseSummary($evidence),
            'first_time_winner' => "First win, at start {$evidence['start_number']}".($evidence['one_hit_wonder'] ? ' (only career win)' : ", {$evidence['career_wins']} wins in all"),
            'win_drought_ended' => "First win after {$evidence['starts_before']} starts",
            'podium_drought_ended' => "First podium after {$evidence['starts_before']} starts",
            'most_retirements' => "{$evidence['retirements']} of {$evidence['starters']} starters retired ({$evidence['top_statuses']})",
            'curious_retirement' => "{$evidence['status']}, after {$evidence['laps_completed']} laps",
            'disqualification' => self::disqualificationSummary($evidence),
            'shortest_race' => self::shortestRaceSummary($evidence),
            'dominant_win' => self::dominantSummary($evidence),
            'closest_finish' => 'Won by '.self::seconds($evidence['gap_ms'], 3)." s from {$evidence['runner_up']}",
            'title_decider' => "{$evidence['champion']} champion by ".self::points($evidence['final_margin'])." over {$evidence['runner_up']}".($evidence['lead_change'] ? ", {$evidence['leader_before_final']} led before the final race" : ''),
            'age_record' => Str::ucfirst($evidence['records']).", aged {$evidence['age_years']}",
            'start_milestone' => self::ordinal($evidence['start_number']).' start'.($evidence['first_to_reach'] ? ' (first driver to reach it)' : ''),
            'slowest_pit_stop' => 'Stop of '.self::seconds($evidence['duration_ms'], 1).' s ('.round($evidence['ratio'], 1).'× a typical stop) on lap '.$evidence['lap'],
            default => self::fallbackSummary($evidence),
        };
    }

    /**
     * The end of a moment title, e.g. "only 6 cars started".
     */
    public static function titlePhrase(MomentCandidate $candidate): string
    {
        $evidence = $candidate->evidence;

        return match ($candidate->type) {
            'fewest_starters' => "only {$evidence['starters']} cars started",
            'fewest_finishers' => "only {$evidence['running']} of {$evidence['starters']} cars were running at the flag",
            'grid_to_win_climb' => self::climbPhrase($candidate),
            'surprise_winner' => $evidence['team_position_before'] !== null
                ? self::driverName($candidate).' won with a team '.self::ordinal($evidence['team_position_before'])." of {$evidence['teams_before']} in the constructors' standings"
                : self::driverName($candidate).' took the only win of their career',
            'first_time_winner' => self::driverName($candidate).' won for the first time'.($evidence['one_hit_wonder'] ? ' and never again' : ''),
            'win_drought_ended' => self::driverName($candidate)." won for the first time after {$evidence['starts_before']} starts",
            'podium_drought_ended' => self::driverName($candidate)." reached the podium for the first time after {$evidence['starts_before']} starts",
            'most_retirements' => "{$evidence['retirements']} of {$evidence['starters']} starters retired",
            'curious_retirement' => self::driverName($candidate).'\'s race ended with "'.Str::lower($evidence['status']).'"',
            'disqualification' => self::disqualificationPhrase($candidate),
            'shortest_race' => "the race ended after {$evidence['laps']} ".Str::plural('lap', $evidence['laps']).($evidence['half_points'] ? ', with half points' : ''),
            'dominant_win' => self::dominantPhrase($candidate),
            'closest_finish' => self::driverName($candidate)." beat {$evidence['runner_up']} by ".self::seconds($evidence['gap_ms'], 3).' seconds',
            'title_decider' => $evidence['lead_change']
                ? "{$evidence['champion']} took the title from {$evidence['leader_before_final']} in the final race"
                : "{$evidence['champion']} won the title by ".self::points($evidence['final_margin']),
            'age_record' => self::driverName($candidate).' became the '.($evidence['best_rank'] === 1 ? "{$evidence['best_record']} ever" : self::ordinal($evidence['best_rank']).'-'.$evidence['best_record']).", aged {$evidence['age_years']}",
            'start_milestone' => self::driverName($candidate).' made their '.self::ordinal($evidence['start_number']).' start'.($evidence['first_to_reach'] ? ', the first driver to get there' : ''),
            'slowest_pit_stop' => self::driverName($candidate).' spent '.self::seconds($evidence['duration_ms'], 1).' seconds in the pit lane',
            default => Str::lower(self::typeLabel($candidate->type)),
        };
    }

    /**
     * Every piece of evidence as label => readable value.
     *
     * @return array<string, string>
     */
    public static function details(MomentCandidate $candidate): array
    {
        $details = [];

        foreach ($candidate->evidence as $key => $value) {
            $details[self::keyLabel($key)] = match (true) {
                is_bool($value) => $value ? 'Yes' : 'No',
                $value === null => '-',
                is_float($value) => (string) round($value, 3),
                default => (string) $value,
            };
        }

        return $details;
    }

    private static function climbPhrase(MomentCandidate $candidate): string
    {
        $grid = $candidate->evidence['grid'] ?? 0;
        $winner = self::driverName($candidate);

        if ($candidate->evidence['pit_lane_start'] ?? false) {
            return "{$winner} won from the pit lane";
        }

        return "{$winner} won from {$grid}".self::ordinalSuffix((int) $grid).' on the grid';
    }

    private static function driverName(MomentCandidate $candidate): string
    {
        return $candidate->driver ? "{$candidate->driver->given_name} {$candidate->driver->family_name}" : 'the winner';
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function surpriseSummary(array $evidence): string
    {
        $team = $evidence['team_position_before'] !== null
            ? 'Won with a team '.self::ordinal($evidence['team_position_before'])." of {$evidence['teams_before']} in the constructors' standings"
            : null;

        return match (true) {
            $team !== null && $evidence['only_career_win'] => "{$team}, only career win",
            $team !== null => $team,
            default => 'Only career win',
        };
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function disqualificationSummary(array $evidence): string
    {
        $summary = "{$evidence['disqualified']} disqualified: {$evidence['drivers']}";

        return match (true) {
            $evidence['road_position'] !== null => $summary.' (first named finished '.self::ordinal($evidence['road_position']).' on the road)',
            $evidence['full_distance'] => $summary.' (first named ran the full distance)',
            default => $summary,
        };
    }

    private static function disqualificationPhrase(MomentCandidate $candidate): string
    {
        $evidence = $candidate->evidence;
        $driver = $candidate->driver ? "{$candidate->driver->given_name} {$candidate->driver->family_name}" : 'a driver';

        return match (true) {
            $evidence['road_position'] === 1 => "{$driver} was disqualified after finishing first on the road",
            $evidence['road_position'] !== null && $evidence['road_position'] <= 3 => "{$driver} was disqualified after finishing ".self::ordinal($evidence['road_position']).' on the road',
            $evidence['disqualified'] > 1 => "{$evidence['disqualified']} drivers were disqualified",
            default => "{$driver} was disqualified",
        };
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function shortestRaceSummary(array $evidence): string
    {
        $laps = $evidence['estimated_scheduled_laps'] !== null
            ? "{$evidence['laps']} of about ".round($evidence['estimated_scheduled_laps']).' laps'
            : "{$evidence['laps']} laps";

        return $laps.($evidence['half_points'] ? ', half points' : '');
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function dominantSummary(array $evidence): string
    {
        if ($evidence['laps_ahead'] >= 1) {
            return "Won by {$evidence['laps_ahead']} ".Str::plural('lap', $evidence['laps_ahead'])." from {$evidence['runner_up']}";
        }

        return ($evidence['gap_ms'] !== null ? 'Won by '.self::seconds($evidence['gap_ms'], 1).' s, ' : 'Won, ')
            ."{$evidence['lapped_rivals']} of {$evidence['classified_rivals']} classified rivals lapped";
    }

    private static function dominantPhrase(MomentCandidate $candidate): string
    {
        $evidence = $candidate->evidence;

        return match (true) {
            $evidence['laps_ahead'] >= 1 => self::driverName($candidate).' lapped the whole field',
            $evidence['gap_ms'] !== null && $evidence['lapped_rivals'] < $evidence['classified_rivals'] * 0.9 => self::driverName($candidate).' won by '.self::seconds($evidence['gap_ms'], 1).' seconds',
            default => self::driverName($candidate)." lapped {$evidence['lapped_rivals']} of {$evidence['classified_rivals']} classified rivals",
        };
    }

    private static function seconds(int $millis, int $decimals): string
    {
        return number_format($millis / 1000, $decimals, '.', '');
    }

    private static function points(float|int $points): string
    {
        $formatted = rtrim(rtrim(number_format((float) $points, 2, '.', ''), '0'), '.');

        return $formatted.' '.Str::plural('point', (float) $points === 1.0 ? 1 : 2);
    }

    private static function ordinal(int $number): string
    {
        return $number.self::ordinalSuffix($number);
    }

    private static function ordinalSuffix(int $number): string
    {
        return match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };
    }

    private static function keyLabel(string $key): string
    {
        return match ($key) {
            'raw_score' => 'Raw score (before ranking)',
            'ratio' => 'Ratio',
            default => Str::headline($key),
        };
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private static function fallbackSummary(array $evidence): string
    {
        return collect($evidence)
            ->except('raw_score')
            ->map(fn ($value, $key) => Str::headline($key).': '.(is_bool($value) ? ($value ? 'yes' : 'no') : $value))
            ->implode(', ');
    }
}
