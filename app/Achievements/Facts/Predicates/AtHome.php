<?php

namespace App\Achievements\Facts\Predicates;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Fact;
use App\Achievements\Facts\Predicate;
use App\Achievements\SessionData;
use App\Models\NationalityCountry;

/**
 * The driver raced at home (Hometown Hero): their nationality is at home in
 * the circuit's country, through the nationality-to-country table. The
 * circuit decides, not the Grand Prix's name, so Imola counts as Italy and
 * every race in the USA is home for an American.
 */
class AtHome implements Predicate
{
    /**
     * @var array<string, string>|null
     */
    private ?array $homeCountries = null;

    public function sources(): array
    {
        return [DataSource::JolpicaResults];
    }

    public function availableFrom(): int
    {
        return 1950;
    }

    public function notes(): array
    {
        return ['Driver nationality against the circuit\'s country, through the editable nationality table (CMS: Nationalities).'];
    }

    public function test(Fact $fact, SessionData $data): bool
    {
        $nationality = $data->entry($fact->entryId)?->driver?->nationality;
        $country = $data->session->round->circuit?->country;
        $this->homeCountries ??= NationalityCountry::homeCountries();

        return $nationality !== null && $country !== null && ($this->homeCountries[$nationality] ?? null) === $country;
    }
}
