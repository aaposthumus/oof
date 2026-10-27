<?php

namespace Database\Seeders;

use App\Enums\SensitivityLevel;
use App\Enums\SensitivitySeverity;
use App\Models\Driver;
use App\Models\MomentSensitivity;
use App\Models\Round;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * World Championship weekends on which someone was fatally injured in
 * connection with the event: drivers, marshals, spectators, team members and
 * drivers in support races or testing just before it. Also weekends on which
 * someone was seriously injured (hospitalised or left with a lasting injury,
 * or, when in doubt, reported as injured), including media. All are seeded as
 * sensitive, never blocked. Sensitive is only a review flag, so uncertain
 * entries are included too.
 *
 * Fatal entries cover the whole round. An injury to an F1 driver names the
 * driver, so stories spanning the season (a title decider) only flag it when
 * that driver is involved; injuries to others cover the whole round.
 *
 * Run with: php artisan db:seed --class=MomentSensitivitySeeder
 * Safe to re-run: it updates notes but keeps a level that was changed by hand.
 * Injury-only weekends once had a round-level fatal row holding the injury
 * note; those unchanged rows are removed.
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
        '1959|German Grand Prix' => 'Jean Behra was killed in a crash in a support race at the AVUS the day before the race.',
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
        '1980|German Grand Prix' => 'Patrick Depailler was killed in a crash during a private test at Hockenheim, shortly before the race.',
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
     * Injuries: weekend key, the Jolpica id of the F1 driver injured (null when the injured are not F1 drivers), note
     * and optionally the severity: injury (the default) or minor (only precautionary hospital checks).
     *
     * @var list<array{0: string, 1: string|null, 2: string, 3?: SensitivitySeverity}>
     */
    public const INJURIES = [
        ['1955|Monaco Grand Prix', 'ascari', 'Alberto Ascari\'s car went into the harbour during the race; he escaped with a nose injury.'],
        ['1960|Belgian Grand Prix', 'moss', 'Stirling Moss suffered broken legs and back injuries in a practice crash.'],
        ['1960|Belgian Grand Prix', 'mike_taylor', 'Mike Taylor was seriously injured in a practice crash.'],
        ['1962|Belgian Grand Prix', 'mairesse', 'Willy Mairesse\'s car landed upside down and caught fire after a collision during the race; reports differ on how badly he was hurt.'],
        ['1966|Belgian Grand Prix', 'stewart', 'Jackie Stewart was trapped in the fuel-soaked car after a first-lap crash and was taken to hospital.'],
        ['1966|German Grand Prix', 'ligier', 'Guy Ligier suffered a broken kneecap in a practice crash; doctors considered amputating the leg.'],
        ['1968|Canadian Grand Prix', 'ickx', 'Jacky Ickx broke a leg in a practice crash after the throttle stuck open.'],
        ['1969|Spanish Grand Prix', 'rindt', 'Jochen Rindt suffered a broken nose and bruises when his car overturned during the race; hospital treatment is unconfirmed.'],
        ['1969|United States Grand Prix', 'hill', 'Graham Hill broke both legs when thrown from the car in a crash during the race.'],
        ['1970|Spanish Grand Prix', 'ickx', 'Jacky Ickx suffered burns when his car caught fire after a first-lap collision; reports differ on how serious they were.'],
        ['1972|French Grand Prix', 'marko', 'Helmut Marko lost the sight of one eye when a stone pierced the visor during the race.'],
        ['1973|British Grand Prix', 'adamich', 'Andrea de Adamich was trapped in the car for a long time after a first-lap pile-up and suffered leg injuries that ended the Formula One career.'],
        ['1974|German Grand Prix', 'hailwood', 'Mike Hailwood broke a leg in a crash during the race, which ended the Formula One career.'],
        ['1975|Spanish Grand Prix', 'stommelen', 'Rolf Stommelen was seriously injured in the crash that killed people beside the track.'],
        ['1976|German Grand Prix', 'lauda', 'Niki Lauda suffered severe burns and lung damage in a fiery crash during the race.'],
        ['1977|Japanese Grand Prix', null, 'Several other people in a prohibited area beside the track were injured in the same crash.'],
        ['1978|Italian Grand Prix', 'brambilla', 'Vittorio Brambilla suffered a serious head injury in the same start crash.'],
        ['1980|Canadian Grand Prix', 'jabouille', 'Jean-Pierre Jabouille broke both legs in a crash during the race.'],
        ['1980|United States Grand Prix West', 'regazzoni', 'Clay Regazzoni was left paralysed from the waist down by a crash during the race.'],
        ['1981|Belgian Grand Prix', null, 'Arrows mechanic Dave Luckett was hit by a car on the grid at the start and suffered two broken legs.'],
        ['1982|German Grand Prix', 'pironi', 'Didier Pironi suffered severe leg injuries in a practice crash, which ended the racing career.'],
        ['1982|Swiss Grand Prix', 'tambay', 'Patrick Tambay did not start because of a back problem (a pinched nerve).'],
        ['1984|Dallas Grand Prix', 'brundle', 'Martin Brundle broke both ankles and feet in a crash during qualifying.'],
        ['1985|French Grand Prix', 'mansell', 'Nigel Mansell suffered concussion in a crash at Signes during qualifying and did not start; a hospital stay is unconfirmed.'],
        ['1986|British Grand Prix', 'laffite', 'Jacques Laffite broke both legs in a first-lap crash, which ended the Formula One career.'],
        ['1987|San Marino Grand Prix', 'piquet', 'Nelson Piquet suffered concussion in a crash at Tamburello during practice and did not start; a hospital stay is unconfirmed.'],
        ['1988|Detroit Grand Prix', 'capelli', 'Ivan Capelli injured a foot in a practice crash and did not start.'],
        ['1989|San Marino Grand Prix', 'berger', 'Gerhard Berger suffered burns and a broken rib when the car caught fire after a crash during the race.'],
        ['1990|Spanish Grand Prix', 'donnelly', 'Martin Donnelly was critically injured in a practice crash.'],
        ['1993|Belgian Grand Prix', 'zanardi', 'Alessandro Zanardi was taken to hospital with concussion after a heavy practice crash.'],
        ['1994|Monaco Grand Prix', 'wendlinger', 'Karl Wendlinger was left in a coma by a practice crash.'],
        ['1994|San Marino Grand Prix', 'barrichello', 'Rubens Barrichello was taken to hospital after a practice crash.'],
        ['1994|San Marino Grand Prix', null, 'Spectators were injured by debris from a start crash, and mechanics were struck by a loose wheel in the pit lane.'],
        ['1994|Spanish Grand Prix', 'montermini', 'Andrea Montermini broke a heel and a foot in a practice crash.'],
        ['1995|Australian Grand Prix', 'hakkinen', 'Mika Häkkinen suffered a fractured skull in a practice crash and needed an emergency tracheotomy beside the track.'],
        ['1996|Monaco Grand Prix', 'montermini', 'Andrea Montermini is recorded as retiring from the race with an injury; the circumstances are unconfirmed.'],
        ['1997|Japanese Grand Prix', 'morbidelli', 'Gianni Morbidelli is recorded as not starting because of an injury; the circumstances are unconfirmed.'],
        ['1999|British Grand Prix', 'michael_schumacher', 'Michael Schumacher broke a leg in a first-lap crash.'],
        ['2001|Belgian Grand Prix', 'burti', 'Luciano Burti was taken to hospital with a severe concussion after a crash during the race.'],
        ['2002|French Grand Prix', 'fisichella', 'Giancarlo Fisichella was taken to hospital for checks after a heavy practice crash and did not start; reports differ on whether he was concussed.', SensitivitySeverity::Minor],
        ['2002|Japanese Grand Prix', 'mcnish', 'Allan McNish crashed heavily in qualifying, was taken for medical checks with bruising and did not start.', SensitivitySeverity::Minor],
        ['2003|Brazilian Grand Prix', 'alonso', 'Fernando Alonso hit debris from Mark Webber\'s accident during the race and was taken to hospital for checks.', SensitivitySeverity::Minor],
        ['2003|Monaco Grand Prix', 'button', 'Jenson Button was taken to hospital with concussion after a practice crash and did not start the race.'],
        ['2004|United States Grand Prix', 'ralf_schumacher', 'Ralf Schumacher suffered two cracked vertebrae in a crash during the race.'],
        ['2007|Canadian Grand Prix', 'kubica', 'Robert Kubica was taken to hospital with concussion and a sprained ankle after a heavy crash during the race.'],
        ['2009|Hungarian Grand Prix', 'massa', 'Felipe Massa suffered a fractured skull when struck by a spring in qualifying.'],
        ['2009|Japanese Grand Prix', 'glock', 'Timo Glock was taken to hospital with a leg wound after a qualifying crash; a cracked vertebra was found later.'],
        ['2011|Monaco Grand Prix', 'perez', 'Sergio Pérez was taken to hospital with concussion after a qualifying crash.'],
        ['2013|German Grand Prix', null, 'Cameraman Paul Allen was hit by a loose wheel in the pit lane and taken to hospital with a broken collarbone and broken ribs.'],
        ['2016|Australian Grand Prix', 'alonso', 'Fernando Alonso suffered rib fractures and a partially collapsed lung in a heavy crash during the race and missed the next race.'],
        ['2018|Bahrain Grand Prix', null, 'Ferrari mechanic Francesco Cigarini broke a leg when struck by a car leaving a pit stop.'],
        ['2019|Belgian Grand Prix', null, 'Formula 2 driver Juan Manuel Correa was seriously injured in the same crash.'],
        ['2020|Bahrain Grand Prix', 'grosjean', 'Romain Grosjean was taken to hospital with burns after the car split and caught fire in a first-lap crash.'],
        ['2021|British Grand Prix', 'max_verstappen', 'Max Verstappen was taken to hospital for checks after a first-lap crash and was released the same day.', SensitivitySeverity::Minor],
        ['2022|British Grand Prix', 'zhou', 'Guanyu Zhou\'s car flipped in a first-lap crash; he was checked at the medical centre and reports differ on a hospital visit.', SensitivitySeverity::Minor],
        ['2022|British Grand Prix', 'albon', 'Alexander Albon was taken to hospital for precautionary checks after the same first-lap crash.', SensitivitySeverity::Minor],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->removeLegacyInjuryRows();

        foreach (self::WEEKENDS as $key => $note) {
            $this->seed($key, null, SensitivitySeverity::Fatal, $note);
        }

        foreach (self::INJURIES as $entry) {
            [$key, $jolpicaDriverId, $note] = $entry;
            $severity = $entry[3] ?? SensitivitySeverity::Injury;
            $driverId = $jolpicaDriverId === null
                ? null
                : Driver::query()->where('jolpica_driver_id', $jolpicaDriverId)->value('id') ?? throw new RuntimeException("Driver not found: {$jolpicaDriverId}");

            MomentSensitivity::query()
                ->where('round_id', $this->round($key)->id)
                ->where('driver_id', $driverId)
                ->whereIn('severity', [SensitivitySeverity::Injury, SensitivitySeverity::Minor])
                ->where('severity', '!=', $severity)
                ->where('level', SensitivityLevel::Sensitive)
                ->delete();

            $this->seed($key, $driverId, $severity, $note);
        }
    }

    private function seed(string $key, ?int $driverId, SensitivitySeverity $severity, string $note): void
    {
        $sensitivity = MomentSensitivity::firstOrNew(
            ['round_id' => $this->round($key)->id, 'driver_id' => $driverId, 'severity' => $severity],
            ['level' => SensitivityLevel::Sensitive],
        );
        $sensitivity->note = $note;
        $sensitivity->save();
    }

    /**
     * The first version seeded injury-only weekends as a round-level row
     * (which the migration made fatal). Remove those that were not changed
     * by hand; weekends with a fatal entry get their note rewritten anyway.
     */
    private function removeLegacyInjuryRows(): void
    {
        $roundIds = collect(self::INJURIES)
            ->pluck(0)
            ->unique()
            ->reject(fn (string $key) => isset(self::WEEKENDS[$key]))
            ->map(fn (string $key) => $this->round($key)->id);

        MomentSensitivity::query()
            ->whereIn('round_id', $roundIds)
            ->whereNull('driver_id')
            ->where('severity', SensitivitySeverity::Fatal)
            ->where('level', SensitivityLevel::Sensitive)
            ->delete();
    }

    private function round(string $key): Round
    {
        [$year, $name] = explode('|', $key);

        return Round::query()
            ->whereRelation('season', 'year', $year)
            ->where('name', $name)
            ->first() ?? throw new RuntimeException("Round not found: {$year} {$name}");
    }
}
