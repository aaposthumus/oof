<?php

namespace App\Normalizers\OpenF1\Support;

use Carbon\CarbonImmutable;

/**
 * Converts OpenF1 values: ISO 8601 timestamps and durations in seconds.
 */
final class OpenF1Time
{
    /**
     * "2026-10-25T20:03:49.706000+00:00" becomes "2026-10-25 20:03:49.706" (UTC).
     */
    public static function toDateTime(?string $timestamp): ?string
    {
        return $timestamp === null ? null : CarbonImmutable::parse($timestamp)->utc()->format('Y-m-d H:i:s.v');
    }

    public static function parse(string $timestamp): CarbonImmutable
    {
        return CarbonImmutable::parse($timestamp)->utc();
    }

    public static function toMillis(int|float|null $seconds): ?int
    {
        return $seconds === null ? null : (int) round($seconds * 1000);
    }
}
