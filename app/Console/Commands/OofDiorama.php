<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class OofDiorama extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:diorama
        {circuit? : Jolpica circuitId to render, as listed in tools/diorama/circuits.json}
        {--all : Render every circuit in circuits.json}
        {--fresh : Rebuild the circuit data (prep.mjs) before rendering}
        {--out= : Write the PNGs to this folder (default resources/images/origami/dioramas)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render the paper track dioramas with tools/diorama/render.mjs (setup: tools/diorama/README.md)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $circuit = $this->argument('circuit');

        if (($circuit === null) === ! $this->option('all')) {
            $this->components->error('Give one circuitId or --all, not both.');

            return self::INVALID;
        }

        $command = ['node', base_path('tools/diorama/render.mjs'), $circuit ?? '--all'];

        if ($this->option('fresh')) {
            $command[] = '--fresh';
        }

        if ($this->option('out') !== null) {
            $command[] = '--out='.$this->option('out');
        }

        $result = Process::forever()->run($command, function (string $type, string $output): void {
            $this->output->write($output);
        });

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }
}
