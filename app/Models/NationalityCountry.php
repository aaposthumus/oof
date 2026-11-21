<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Which country a driver's nationality is at home in: Jolpica's nationality
 * ("British") and the circuit country as Jolpica names it ("UK"). Read by
 * the at_home predicate (Hometown Hero); editable in the CMS.
 */
#[Fillable(['nationality', 'country', 'note'])]
class NationalityCountry extends Model
{
    /**
     * The country each nationality is at home in.
     *
     * @return array<string, string> Keyed by nationality
     */
    public static function homeCountries(): array
    {
        return self::query()->pluck('country', 'nationality')->all();
    }
}
