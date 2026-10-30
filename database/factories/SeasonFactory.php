<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * The next year handed out, counting up from a year far after any real
     * season, so a generated season never clashes with a year a test names.
     */
    private static int $nextYear = 3000;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => self::$nextYear++,
        ];
    }
}
