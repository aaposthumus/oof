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
 * drivers in support races. Also weekends on which someone was seriously
 * injured (hospitalised or left with a lasting injury), including media. All
 * are seeded as sensitive, never blocked. Sensitive is only a review flag,
 * so uncertain fatal entries are included too; uncertain injuries are not.
 *
 * Run with: php artisan db:seed --class=MomentSensitivitySeeder
 * Safe to re-run: it updates notes but keeps a level that was changed by hand.
 * A weekend in both lists gets one note: the fatal note, then the injury note.
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
     * Serious, non-fatal injuries, keyed like WEEKENDS.
     *
     * @var array<string, string>
     */
    public const INJURY_WEEKENDS = [
        '1960|Belgian Grand Prix' => 'Stirling Moss suffered broken legs and back injuries in a practice crash, and Michael Taylor was seriously injured in another practice crash.',
        '1966|Belgian Grand Prix' => 'Jackie Stewart was trapped in the fuel-soaked car after a first-lap crash and was taken to hospital.',
        '1966|German Grand Prix' => 'Guy Ligier suffered a broken kneecap in a practice crash; doctors considered amputating the leg.',
        '1968|Canadian Grand Prix' => 'Jacky Ickx broke a leg in a practice crash after the throttle stuck open.',
        '1969|United States Grand Prix' => 'Graham Hill broke both legs when thrown from the car in a crash during the race.',
        '1972|French Grand Prix' => 'Helmut Marko lost the sight of one eye when a stone pierced the visor during the race.',
        '1973|British Grand Prix' => 'Andrea de Adamich was trapped in the car for a long time after a first-lap pile-up and suffered leg injuries that ended the Formula One career.',
        '1974|German Grand Prix' => 'Mike Hailwood broke a leg in a crash during the race, which ended the Formula One career.',
        '1975|Spanish Grand Prix' => 'Rolf Stommelen was seriously injured in the same crash, and other people beside the track were injured.',
        '1976|German Grand Prix' => 'Niki Lauda suffered severe burns and lung damage in a fiery crash during the race.',
        '1977|Japanese Grand Prix' => 'Several other people in a prohibited area beside the track were injured in the same crash.',
        '1978|Italian Grand Prix' => 'Vittorio Brambilla suffered a serious head injury in the same start crash.',
        '1980|United States Grand Prix West' => 'Clay Regazzoni was left paralysed from the waist down by a crash during the race.',
        '1980|Canadian Grand Prix' => 'Jean-Pierre Jabouille broke both legs in a crash during the race.',
        '1981|Belgian Grand Prix' => 'Arrows mechanic Dave Luckett was hit by a car on the grid at the start and suffered two broken legs.',
        '1982|German Grand Prix' => 'Didier Pironi suffered severe leg injuries in a practice crash, which ended the racing career.',
        '1984|Dallas Grand Prix' => 'Martin Brundle broke both ankles and feet in a crash during qualifying.',
        '1986|British Grand Prix' => 'Jacques Laffite broke both legs in a first-lap crash, which ended the Formula One career.',
        '1989|San Marino Grand Prix' => 'Gerhard Berger suffered burns and a broken rib when the car caught fire after a crash during the race.',
        '1990|Spanish Grand Prix' => 'Martin Donnelly was critically injured in a practice crash.',
        '1993|Belgian Grand Prix' => 'Alessandro Zanardi was taken to hospital with concussion after a heavy practice crash.',
        '1994|San Marino Grand Prix' => 'Rubens Barrichello was taken to hospital after a practice crash. Spectators were injured by debris from a start crash, and mechanics were struck by a loose wheel in the pit lane.',
        '1994|Monaco Grand Prix' => 'Karl Wendlinger was left in a coma by a practice crash.',
        '1994|Spanish Grand Prix' => 'Andrea Montermini broke a heel and a foot in a practice crash.',
        '1995|Australian Grand Prix' => 'Mika Häkkinen suffered a fractured skull in a practice crash and needed an emergency tracheotomy beside the track.',
        '1999|British Grand Prix' => 'Michael Schumacher broke a leg in a first-lap crash.',
        '2001|Belgian Grand Prix' => 'Luciano Burti was taken to hospital with a severe concussion after a crash during the race.',
        '2003|Monaco Grand Prix' => 'Jenson Button was taken to hospital with concussion after a practice crash and did not start the race.',
        '2004|United States Grand Prix' => 'Ralf Schumacher suffered two cracked vertebrae in a crash during the race.',
        '2007|Canadian Grand Prix' => 'Robert Kubica was taken to hospital with concussion and a sprained ankle after a heavy crash during the race.',
        '2009|Hungarian Grand Prix' => 'Felipe Massa suffered a fractured skull when struck by a spring in qualifying.',
        '2009|Japanese Grand Prix' => 'Timo Glock was taken to hospital with a leg wound after a qualifying crash; a cracked vertebra was found later.',
        '2011|Monaco Grand Prix' => 'Sergio Pérez was taken to hospital with concussion after a qualifying crash.',
        '2013|German Grand Prix' => 'Cameraman Paul Allen was hit by a loose wheel in the pit lane and taken to hospital with a broken collarbone and broken ribs.',
        '2018|Bahrain Grand Prix' => 'Ferrari mechanic Francesco Cigarini broke a leg when struck by a car leaving a pit stop.',
        '2019|Belgian Grand Prix' => 'Formula 2 driver Juan Manuel Correa was seriously injured in the same crash.',
        '2020|Bahrain Grand Prix' => 'Romain Grosjean was taken to hospital with burns after the car split and caught fire in a first-lap crash.',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (array_keys([...self::WEEKENDS, ...self::INJURY_WEEKENDS]) as $key) {
            $note = implode(' ', array_filter([self::WEEKENDS[$key] ?? null, self::INJURY_WEEKENDS[$key] ?? null]));
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
