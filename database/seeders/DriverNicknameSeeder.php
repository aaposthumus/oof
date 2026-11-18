<?php

namespace Database\Seeders;

use App\Enums\NicknameStatus;
use App\Models\Driver;
use App\Models\DriverNickname;
use Illuminate\Database\Seeder;

/**
 * The nicknames, mottos and planned evolutions of the grid and the legends,
 * from docs/oof-concept.md (13 November 2026), all as drafts.
 *
 * Create-only: a driver's stage that exists is never touched, so whatever
 * the CMS corrected stays. A driver not in the database yet (matched on the
 * Jolpica ID, else the name) is skipped and reported; run it again once the
 * driver is imported.
 */
class DriverNicknameSeeder extends Seeder
{
    /**
     * Jolpica driver ID, given and family name, stage 1 [nickname, motto],
     * and the planned stage 2 [nickname, motto] or null.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: array{0: string, 1: string|null}, 4: array{0: string, 1: string|null}|null}>
     */
    public const NICKNAMES = [
        ['max_verstappen', 'Max', 'Verstappen', ['MaxSettings', 'Everything on max'], null],
        ['hadjar', 'Isack', 'Hadjar', ['HadYouThere', 'Gotcha, every time.'], null],
        ['russell', 'George', 'Russell', ['GoldRush', 'Always dressed for the podium'], null],
        ['antonelli', 'Andrea Kimi', 'Antonelli', ['Kimissimo', 'One scoop and counting'], null],
        ['leclerc', 'Charles', 'Leclerc', ['LeClutch', 'Hits the right notes'], null],
        ['hamilton', 'Lewis', 'Hamilton', ['HammerOn', "Let's keep hammering"], null],
        ['norris', 'Lando', 'Norris', ['SoftLanding', 'Always sticks the landing'], null],
        ['piastri', 'Oscar', 'Piastri', ['Plasteri', 'Lets his driving do the talking'], null],
        ['lawson', 'Liam', 'Lawson', ['Outlawson', 'Stands his ground'], ['LawFirm', 'Stands his ground']],
        ['arvid_lindblad', 'Arvid', 'Lindblad', ['LindBud', 'Born to bloom'], ['LindBloom', null]],
        ['gasly', 'Pierre', 'Gasly', ['Gasmania', 'Thrives under Pierre pressure'], null],
        ['colapinto', 'Franco', 'Colapinto', ['FranTastic', 'Louder than the grandstand'], null],
        ['rafael_camara', 'Rafael', 'Câmara', ['RafaCut', 'Always Camara-ready'], ['Rafined', null]],
        ['bearman', 'Oliver', 'Bearman', ['BearlyBraking', 'Bear with me'], null],
        ['hulkenberg', 'Nico', 'Hülkenberg', ['Peako', 'The view was worth the climb'], null],
        ['bortoleto', 'Gabriel', 'Bortoleto', ['Gabbles', 'Bubbling up'], ['Gabblefly', null]],
        ['albon', 'Alexander', 'Albon', ['Alexactly', 'Right on the dot'], null],
        ['sainz', 'Carlos', 'Sainz', ['Smoothie', 'Operating one move ahead'], null],
        ['alonso', 'Fernando', 'Alonso', ['PlanNando', "Won't retire before #33"], null],
        ['stroll', 'Lance', 'Stroll', ['RainLance', 'Bring on the rain'], null],
        ['perez', 'Sergio', 'Pérez', ['ChecoMate', 'Checo your mirrors'], null],
        ['bottas', 'Valtteri', 'Bottas', ['BottomsUp', 'Cheers to that'], null],
        // Legends, for throwback posts.
        ['mansell', 'Nigel', 'Mansell', ['Manstache', null], null],
        ['hakkinen', 'Mika', 'Häkkinen', ['Sighkkinen', null], null],
        ['michael_schumacher', 'Michael', 'Schumacher', ['SchuTime', null], null],
        ['lauda', 'Niki', 'Lauda', ['LaudAndClear', null], null],
    ];

    /**
     * The entries whose driver was not found on the last run.
     *
     * @var list<string>
     */
    public array $skipped = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->skipped = [];

        foreach (self::NICKNAMES as [$jolpicaId, $givenName, $familyName, $current, $next]) {
            $driver = Driver::query()->firstWhere('jolpica_driver_id', $jolpicaId)
                ?? Driver::query()->where('given_name', $givenName)->where('family_name', $familyName)->first();

            if ($driver === null) {
                $this->skipped[] = "{$givenName} {$familyName} ({$current[0]})";

                continue;
            }

            $first = DriverNickname::query()->firstOrCreate(
                ['driver_id' => $driver->id, 'stage' => 1],
                ['nickname' => $current[0], 'motto' => $current[1], 'status' => NicknameStatus::Active, 'is_draft' => true],
            );

            if ($next !== null) {
                DriverNickname::query()->firstOrCreate(
                    ['driver_id' => $driver->id, 'stage' => 2],
                    ['nickname' => $next[0], 'motto' => $next[1], 'status' => NicknameStatus::Planned, 'evolves_from_id' => $first->id, 'is_draft' => true],
                );
            }
        }

        if ($this->skipped !== []) {
            $this->command?->warn('Not in the database yet, skipped: '.implode(', ', $this->skipped).'.');
        }
    }
}
