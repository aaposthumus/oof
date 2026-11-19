<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Driver;
use App\Models\DriverEntry;
use App\Models\LapTime;
use App\Models\RaceSession;
use App\Models\Round;
use App\Models\Season;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsJolpicaDumps;
use Tests\TestCase;

class OofImportDumpTest extends TestCase
{
    use BuildsJolpicaDumps, LazilyRefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/oof-dumps-'.uniqid();
        config(['jolpica.dumps.directory' => $this->directory]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_fetches_the_free_dump_checks_its_hash_and_imports_the_laps(): void
    {
        $entry = $this->raceEntry();
        $zip = file_get_contents($this->jolpicaDumpZip($this->tables()));
        $this->fakeJolpica($zip, hash('sha256', $zip));

        $this->artisan('oof:import:dump')
            ->expectsOutputToContain('Fetched free Jolpica dump of 2026-11-02')
            ->expectsOutputToContain('1 laps in 1 races')
            ->assertSuccessful();

        $this->assertSame(1, LapTime::where('driver_entry_id', $entry->id)->count());
        $this->assertFileExists($this->directory.'/jolpica-csv-2026-11-02.zip');
    }

    public function test_reuses_a_stored_dump_with_the_same_hash(): void
    {
        $zip = file_get_contents($this->jolpicaDumpZip($this->tables()));
        File::ensureDirectoryExists($this->directory);
        file_put_contents($this->directory.'/jolpica-csv-2026-11-02.zip', $zip);
        $this->fakeJolpica($zip, hash('sha256', $zip));

        $this->artisan('oof:import:dump')
            ->expectsOutputToContain('Using the stored free Jolpica dump of 2026-11-02')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_refuses_a_download_that_does_not_match_its_hash(): void
    {
        $this->fakeJolpica(file_get_contents($this->jolpicaDumpZip($this->tables())), str_repeat('0', 64));

        $this->artisan('oof:import:dump')
            ->expectsOutputToContain('does not match its published SHA-256')
            ->assertFailed();

        $this->assertSame([], File::glob($this->directory.'/*'));
    }

    public function test_imports_a_dump_given_as_a_file_without_fetching(): void
    {
        $this->raceEntry();

        $this->artisan('oof:import:dump', ['--file' => $this->jolpicaDumpZip($this->tables())])
            ->expectsOutputToContain('1 laps in 1 races')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function fakeJolpica(string $zip, string $hash): void
    {
        Http::fake([
            'api.jolpi.ca/data/dumps/download/' => Http::response(['delayed_dumps' => ['csv' => [
                'file_hash' => $hash,
                'uploaded_at' => '2026-11-02T20:02:42.203913Z',
                'download_url' => 'https://api.jolpi.ca/data/dumps/download/delayed/?dump_type=csv',
            ]]]),
            'api.jolpi.ca/data/dumps/download/delayed/*' => Http::response($zip),
        ]);
    }

    private function raceEntry(): DriverEntry
    {
        $round = Round::factory()->for(Season::factory()->state(['year' => 2010]))->create(['round_number' => 1]);

        return DriverEntry::factory()->create([
            'race_session_id' => RaceSession::factory()->create(['round_id' => $round->id])->id,
            'driver_id' => Driver::factory()->create(['jolpica_driver_id' => 'kubica'])->id,
        ]);
    }

    /**
     * @return array<string, list<array<string, int|string>>>
     */
    private function tables(): array
    {
        return [
            'season' => [['id' => 1, 'year' => 2010]],
            'round' => [['id' => 1, 'season_id' => 1, 'number' => 1]],
            'session' => [['id' => 1, 'round_id' => 1, 'type' => 'R']],
            'driver' => [['id' => 1, 'reference' => 'kubica']],
            'teamdriver' => [['id' => 1, 'driver_id' => 1]],
            'roundentry' => [['id' => 1, 'team_driver_id' => 1]],
            'sessionentry' => [['id' => 1, 'session_id' => 1, 'round_entry_id' => 1]],
            'lap' => [['session_entry_id' => 1, 'number' => '1', 'position' => '2', 'time' => '00:02:01.500']],
        ];
    }
}
