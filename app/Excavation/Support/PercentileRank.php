<?php

namespace App\Excavation\Support;

/**
 * Turns raw scores into percentile ranks: the best score gets 100, the lowest
 * 0, and equal scores share the higher rank. A single score ranks 100.
 */
class PercentileRank
{
    /**
     * @param  array<int|string, float>  $scores
     * @return array<int|string, float> Same keys, ranks of 0-100 with two decimals
     */
    public static function of(array $scores): array
    {
        $count = count($scores);

        if ($count <= 1) {
            return array_fill_keys(array_keys($scores), 100.0);
        }

        $scores = array_map(fn (float $score) => round($score, 2), $scores);
        asort($scores);

        $keys = array_keys($scores);
        $ranks = [];

        for ($start = 0; $start < $count; $start = $end + 1) {
            $end = $start;

            while ($end + 1 < $count && $scores[$keys[$end + 1]] === $scores[$keys[$start]]) {
                $end++;
            }

            for ($index = $start; $index <= $end; $index++) {
                $ranks[$keys[$index]] = round($end / ($count - 1) * 100, 2);
            }
        }

        return $ranks;
    }
}
