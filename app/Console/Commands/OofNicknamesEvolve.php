<?php

namespace App\Console\Commands;

use App\Enums\EvolutionStatus;
use App\Models\NicknameEvolution;
use Illuminate\Console\Command;

class OofNicknamesEvolve extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:nicknames:evolve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply the approved nickname evolutions whose effective date has come, and dispatch NicknameEvolved';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $due = NicknameEvolution::query()
            ->with(['from', 'to', 'season'])
            ->where('status', EvolutionStatus::Approved)
            ->whereDate('effective_on', '<=', today())
            ->orderBy('effective_on')
            ->get();

        foreach ($due as $evolution) {
            $evolution->apply();
            $this->components->info("{$evolution->from->nickname} is now {$evolution->to->nickname}.");
        }

        if ($due->isEmpty()) {
            $this->components->info('No evolutions due.');
        }

        return self::SUCCESS;
    }
}
