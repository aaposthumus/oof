<?php

namespace App\Normalizers\Jolpica\Support;

class TimeParser
{
    /**
     * Convert a time such as "1:38.406", "23.004" or "1:40:52.554" to milliseconds.
     * Returns null for empty or unparseable values such as "DNF" or "+13.773".
     */
    public static function toMillis(?string $time): ?int
    {
        if ($time === null || preg_match('/^(?:(?:(\d+):)?(\d+):)?(\d+)(?:\.(\d{1,3}))?$/', trim($time), $matches) !== 1) {
            return null;
        }

        [$hours, $minutes, $seconds] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        $millis = (int) str_pad($matches[4] ?? '', 3, '0');

        return (($hours * 60 + $minutes) * 60 + $seconds) * 1000 + $millis;
    }
}
