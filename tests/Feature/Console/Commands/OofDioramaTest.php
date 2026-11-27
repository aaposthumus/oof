<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OofDioramaTest extends TestCase
{
    public function test_renders_one_circuit_into_the_default_folder(): void
    {
        Process::fake();

        $this->artisan('oof:diorama', ['circuit' => 'suzuka'])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['node', base_path('tools/diorama/render.mjs'), 'suzuka']);
    }

    public function test_passes_all_fresh_and_out_through(): void
    {
        Process::fake();

        $this->artisan('oof:diorama', ['--all' => true, '--fresh' => true, '--out' => '/tmp/dioramas'])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process) => $process->command === ['node', base_path('tools/diorama/render.mjs'), '--all', '--fresh', '--out=/tmp/dioramas']);
    }

    public function test_passes_an_empty_out_through_so_it_does_not_fall_back_to_the_default_folder(): void
    {
        Process::fake();

        $this->artisan('oof:diorama', ['--all' => true, '--out' => '']);

        Process::assertRan(fn (PendingProcess $process) => in_array('--out=', $process->command, true));
    }

    public function test_rejects_a_run_without_a_circuit_or_all(): void
    {
        Process::fake();

        $this->artisan('oof:diorama')->assertExitCode(2);

        Process::assertNothingRan();
    }

    public function test_rejects_a_circuit_together_with_all(): void
    {
        Process::fake();

        $this->artisan('oof:diorama', ['circuit' => 'suzuka', '--all' => true])->assertExitCode(2);

        Process::assertNothingRan();
    }

    public function test_fails_when_the_renderer_fails(): void
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        $this->artisan('oof:diorama', ['circuit' => 'nowhere'])->assertFailed();
    }
}
