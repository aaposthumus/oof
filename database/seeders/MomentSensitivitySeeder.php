<?php

namespace Database\Seeders;

use App\Enums\SensitivityLevel;
use App\Models\MomentSensitivity;
use App\Models\Round;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * World Championship weekends on which someone was fatally injured in
 * connection with the event: drivers, marshals, spectators, team members and
 * drivers in support races. All are seeded as sensitive, never blocked.
 * Sensitive is only a review flag, so uncertain entries are included too.
 *
 * Run with: php artisan db:seed --class=MomentSensitivitySeeder
 * Safe to re-run: it updates notes but keeps a level that was changed by hand.
 */
class MomentSensitivitySeeder extends Seeder
{
    /**
     * Keyed by "year|round name".
     *
     * @var array<string, string>
     */
    public const WEEKENDS = [
        '1953|Argentine Grand Prix' => 'Several spectators were killed when Giuseppe Farina\'s car went into the crowd during the race.',
        '1953|Indianapolis 500' => 'Chet Miller was killed in a practice crash. Carl Scarborough died of heat exhaustion after retiring from the race.',
        '1954|German Grand Prix' => 'Onofre Marimón was killed in a practice crash.',
        '1955|Indianapolis 500' => 'Manuel Ayulo was fatally injured in a practice crash. Bill Vukovich was killed in a crash during the race.',
        '1957|Indianapolis 500' => 'Keith Andrews was killed in a practice crash.',
        '1958|Indianapolis 500' => 'Pat O\'Connor was killed in a first-lap crash.',
        '1958|French Grand Prix' => 'Luigi Musso was fatally injured in a crash during the race.',
        '1958|German Grand Prix' => 'Peter Collins was fatally injured in a crash during the race.',
        '1958|Moroccan Grand Prix' => 'Stuart Lewis-Evans was badly burned in a crash during the race and died six days later.',
        '1959|Indianapolis 500' => 'Jerry Unser was fatally injured in a practice crash. Bob Cortner was killed in a practice crash.',
        '1960|Indianapolis 500' => 'Two spectators were killed when a privately built viewing scaffold collapsed before the start.',
        '1960|Belgian Grand Prix' => 'Chris Bristow and Alan Stacey were killed in separate crashes during the race.',
        '1961|Italian Grand Prix' => 'Wolfgang von Trips and spectators were killed when his car went into the crowd after a collision.',
        '1963|German Grand Prix' => 'A young marshal is reported to have been killed during the event.',
        '1964|German Grand Prix' => 'Carel Godin de Beaufort was fatally injured in a practice crash.',
        '1966|German Grand Prix' => 'John Taylor was badly burned in a first-lap crash and died a month later.',
        '1967|Monaco Grand Prix' => 'Lorenzo Bandini was badly burned in a crash during the race and died three days later.',
        '1968|French Grand Prix' => 'Jo Schlesser was killed in a crash during the race.',
        '1969|German Grand Prix' => 'Gerhard Mitter was killed in a practice crash in a Formula Two car.',
        '1970|Dutch Grand Prix' => 'Piers Courage was killed in a crash during the race.',
        '1970|Italian Grand Prix' => 'Jochen Rindt was killed in a crash in practice.',
        '1973|Dutch Grand Prix' => 'Roger Williamson was killed in a crash during the race.',
        '1973|United States Grand Prix' => 'François Cevert was killed in a crash in practice.',
        '1974|South African Grand Prix' => 'Peter Revson was killed in a crash during a testing session at Kyalami, eight days before the race.',
        '1974|United States Grand Prix' => 'Helmuth Koinigg was killed in a crash during the race.',
        '1975|Spanish Grand Prix' => 'People beside the track, among them a fireman, photojournalists and a spectator, were killed when Rolf Stommelen\'s car left the circuit.',
        '1975|Austrian Grand Prix' => 'Mark Donohue and marshal Manfred Schaller were fatally injured in Donohue\'s warm-up crash.',
        '1976|Dutch Grand Prix' => 'Marshal Ron Lenderink was killed during a support race.',
        '1977|South African Grand Prix' => 'Tom Pryce and marshal Frederik Jansen van Vuuren were killed in a collision during the race.',
        '1977|Japanese Grand Prix' => 'Two people, a marshal and a spectator, were killed when Gilles Villeneuve\'s car left the track after a collision.',
        '1978|Italian Grand Prix' => 'Ronnie Peterson was injured in a crash at the start and died the next day.',
        '1981|Belgian Grand Prix' => 'Osella mechanic Giovanni Amadeo was struck by a car in the pit lane during practice and died days later.',
        '1982|Belgian Grand Prix' => 'Gilles Villeneuve was killed in a crash in qualifying.',
        '1982|Canadian Grand Prix' => 'Riccardo Paletti was killed in a crash at the start.',
        '1994|San Marino Grand Prix' => 'Roland Ratzenberger was killed in a crash in qualifying. Ayrton Senna was killed in a crash during the race.',
        '2000|Italian Grand Prix' => 'Marshal Paolo Gislimberti was struck by debris from a first-lap crash and killed.',
        '2001|Australian Grand Prix' => 'Marshal Graham Beveridge was struck by a wheel from a crash during the race and killed.',
        '2013|Canadian Grand Prix' => 'Marshal Mark Robinson was struck by a recovery vehicle after the race and killed.',
        '2014|Japanese Grand Prix' => 'Jules Bianchi was critically injured in a crash during the race and died nine months later.',
        '2019|Belgian Grand Prix' => 'Formula 2 driver Anthoine Hubert was killed in a crash during the support race.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::WEEKENDS as $key => $note) {
            [$year, $name] = explode('|', $key);

            $round = Round::query()
                ->whereRelation('season', 'year', $year)
                ->where('name', $name)
                ->first() ?? throw new RuntimeException("Round not found: {$year} {$name}");

            $sensitivity = MomentSensitivity::firstOrNew(
                ['round_id' => $round->id, 'driver_id' => null],
                ['level' => SensitivityLevel::Sensitive],
            );
            $sensitivity->note = $note;
            $sensitivity->save();
        }
    }
}
