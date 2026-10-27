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
        $winner = $candidate->driver ? "{$candidate->driver->given_name} {$candidate->driver->family_name}" : 'the winner';

        if ($candidate->evidence['pit_lane_start'] ?? false) {
            return "{$winner} won from the pit lane";
        }

        return "{$winner} won from {$grid}".self::ordinalSuffix((int) $grid).' on the grid';
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
