<?php

namespace App\Console\Commands;

use App\Excavation\Candidate;
use App\Excavation\DigParameters;
use App\Excavation\ExcavationResult;
use App\Excavation\ExcavationRunner;
use App\Models\Driver;
use App\Models\Round;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class OofDig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:dig
        {type? : Only run this excavator (default: all)}
        {--season=* : Only dig in these seasons}
        {--from= : Only dig from this season on}
        {--to= : Only dig up to this season}
        {--dry-run : Show what would be found without storing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dig through F1 history for historic-moment candidates (safe to re-run; updates existing candidates)';

    /**
     * Number of candidates listed per excavator.
     */
    private const TOP = 10;

    /**
     * Execute the console command.
     */
    public function handle(ExcavationRunner $runner): int
    {
        $excavators = $runner->excavators();
        $type = $this->argument('type');

        if ($type !== null && ! isset($excavators[$type])) {
            $this->components->error("Unknown excavator [{$type}]. Available: ".implode(', ', array_keys($excavators)).'.');

            return self::FAILURE;
        }

        $parameters = new DigParameters(
            seasons: array_map('intval', $this->option('season')),
            from: $this->option('from') !== null ? (int) $this->option('from') : null,
            to: $this->option('to') !== null ? (int) $this->option('to') : null,
        );
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry run: nothing is stored.');
        }

        $results = collect($type !== null ? [$excavators[$type]] : $excavators)
            ->map(fn ($excavator) => $runner->run($excavator, $parameters, $dryRun))
            ->values();

        foreach ($results as $result) {
            $this->showResult($result);
        }

        if ($results->count() > 1) {
            $this->newLine();
            $this->components->info('Top '.self::TOP.' across all excavators');
            $this->showCandidates($results
                ->flatMap(fn (ExcavationResult $result) => $result->candidates->map(fn (Candidate $candidate) => [$result->excavator->key(), $candidate]))
                ->sortByDesc(fn (array $typed) => $typed[1]->score)
                ->take(self::TOP));
        }

        return self::SUCCESS;
    }

    private function showResult(ExcavationResult $result): void
    {
        $this->newLine();
        $this->components->info("{$result->excavator->key()} (v{$result->excavator->version()})");

        foreach ($result->stats as $label => $count) {
            $this->components->twoColumnDetail(ucfirst($label), number_format($count));
        }

        $this->showCandidates($result->candidates
            ->take(self::TOP)
            ->map(fn (Candidate $candidate) => [$result->excavator->key(), $candidate]));
    }

    /**
     * @param  Collection<int, array{0: string, 1: Candidate}>  $typedCandidates
     */
    private function showCandidates(Collection $typedCandidates): void
    {
        if ($typedCandidates->isEmpty()) {
            return;
        }

        $candidates = $typedCandidates->map(fn (array $typed) => $typed[1]);
        $rounds = Round::with('season')->findMany($candidates->pluck('roundId'))->keyBy('id');
        $drivers = Driver::findMany($candidates->pluck('driverId')->filter())->keyBy('id');

        $this->table(
            ['#', 'Score', 'Type', 'Round', 'Driver', 'Evidence'],
            $typedCandidates->values()->map(function (array $typed, int $index) use ($rounds, $drivers) {
                [$type, $candidate] = $typed;
                $round = $rounds[$candidate->roundId];
                $driver = $drivers[$candidate->driverId] ?? null;

                return [
                    $index + 1,
                    number_format($candidate->score, 2),
                    $type,
                    "{$round->season->year} {$round->name}".($candidate->isSensitive ? ' <fg=red>⚠ SENSITIVE</>' : ''),
                    $driver !== null ? "{$driver->given_name} {$driver->family_name}" : '',
                    collect($candidate->evidence)->map(fn ($value, string $key) => $key.'='.var_export($value, true))->implode(' '),
                ];
            })->all(),
        );

        foreach ($candidates->where('isSensitive', true)->unique('roundId') as $candidate) {
            $round = $rounds[$candidate->roundId];
            $this->components->warn("Sensitive, {$round->season->year} {$round->name}: {$candidate->sensitivityNote}");
        }
    }
}
