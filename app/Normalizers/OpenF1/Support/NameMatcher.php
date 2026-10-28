<?php

namespace App\Normalizers\OpenF1\Support;

use App\Models\Driver;
use Illuminate\Support\Str;

/**
 * Compares an OpenF1 driver's name with ours, ignoring case, accents and
 * extra given names ("Kimi" is "Andrea Kimi", "Hulkenberg" is "Hülkenberg").
 */
final class NameMatcher
{
    /**
     * @param  array{first_name?: ?string, last_name?: ?string}  $row  An OpenF1 driver
     */
    public static function sameDriver(Driver $driver, array $row): bool
    {
        $lastName = self::tokens($row['last_name'] ?? null);
        $firstName = self::tokens($row['first_name'] ?? null);
        $familyName = self::tokens($driver->family_name);
        $givenName = self::tokens($driver->given_name);

        if ($lastName === [] || $familyName === []) {
            return false;
        }

        $inOrder = $lastName === $familyName && ($firstName === [] || array_intersect($firstName, $givenName) !== []);
        $swapped = $firstName === $familyName && array_intersect($lastName, $givenName) !== [];

        return $inOrder || $swapped;
    }

    /**
     * @return list<string>
     */
    private static function tokens(?string $name): array
    {
        $folded = Str::of(Str::ascii($name ?? ''))->lower()->replaceMatches('/[^a-z]+/', ' ')->squish()->value();

        return $folded === '' ? [] : explode(' ', $folded);
    }
}
