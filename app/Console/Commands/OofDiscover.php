<?php

namespace App\Console\Commands;

use App\Enums\SessionType;
use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OofDiscover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oof:discover
        {--season=* : Only these seasons}
        {--unmatched=50 : How many unmatched wordings to list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Group race control messages into types by pattern and write a discovery report';

    private const EXAMPLES = 3;

    /**
     * Stewards' stages shown per offence, in the order an incident goes through them.
     */
    private const STAGES = [
        MessageType::IncidentNoted, MessageType::UnderInvestigation, MessageType::InvestigatedAfterSession,
        MessageType::NoFurtherAction, MessageType::Warning, MessageType::Reprimand, MessageType::BlackAndWhiteFlag,
        MessageType::TimePenalty, MessageType::DriveThroughPenalty, MessageType::StopGoPenalty, MessageType::PenaltyServed,
    ];

    /**
     * Execute the console command.
     */
    public function handle(MessageClassifier $classifier): int
    {
        $types = [];
        $reasons = [];
        $offences = [];
        $unmatched = [];
        $seasons = [];
        $total = 0;

        $messages = DB::table('race_control_messages')
            ->join('race_sessions', 'race_sessions.id', '=', 'race_control_messages.race_session_id')
            ->join('rounds', 'rounds.id', '=', 'race_sessions.round_id')
            ->join('seasons', 'seasons.id', '=', 'rounds.season_id')
            ->when($this->option('season') !== [], fn ($query) => $query->whereIn('seasons.year', array_map('intval', $this->option('season'))))
            ->orderBy('race_control_messages.id')
            ->select('race_control_messages.id', 'message', 'flag', 'seasons.year', 'rounds.round_number', 'race_sessions.type')
            ->lazyById(2000, 'race_control_messages.id', 'id');

        foreach ($messages as $row) {
            $total++;
            $year = (int) $row->year;
            $seasons[$year] = true;
            $where = "{$year}-{$row->round_number} ".$this->sessionGroup($row->type);
            $classification = $classifier->classify($row->message, $row->flag);

            if ($classification === null) {
                $template = MessageClassifier::template($row->message);
                $unmatched[$template] ??= ['count' => 0, 'seasons' => [], 'example' => "{$row->message} ({$where})"];
                $unmatched[$template]['count']++;
                $unmatched[$template]['seasons'][$year] = true;

                continue;
            }

            $label = $classification->label();
            $types[$label] ??= ['type' => $classification->type, 'count' => 0, 'seasons' => [], 'groups' => [], 'examples' => ['wordings' => [], 'seasons' => []]];
            $types[$label]['count']++;
            $types[$label]['seasons'][$year] = ($types[$label]['seasons'][$year] ?? 0) + 1;
            $group = $this->sessionGroup($row->type);
            $types[$label]['groups'][$group] = ($types[$label]['groups'][$group] ?? 0) + 1;
            $this->collectExample($types[$label]['examples'], "{$row->message} ({$where})", MessageClassifier::template($row->message), $year);

            if ($classification->reason !== null) {
                $reasons[$classification->reason] ??= ['offence' => $classification->offence?->label() ?? '–', 'count' => 0];
                $reasons[$classification->reason]['count']++;
                $offence = $classification->offence?->label() ?? 'Other';
                $offences[$offence]['total'] = ($offences[$offence]['total'] ?? 0) + 1;
                $offences[$offence][$classification->type->value] = ($offences[$offence][$classification->type->value] ?? 0) + 1;
            }
        }

        $seasons = array_keys($seasons);
        sort($seasons);
        // In the classifier's order, sizes of one type by frequency.
        uasort($types, fn (array $a, array $b) => [array_search($a['type'], MessageType::cases(), true), $b['count']] <=> [array_search($b['type'], MessageType::cases(), true), $a['count']]);
        uasort($unmatched, fn (array $a, array $b) => $b['count'] <=> $a['count']);
        uasort($reasons, fn (array $a, array $b) => $b['count'] <=> $a['count']);
        uasort($offences, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        $matchedTotal = array_sum(array_column($types, 'count'));
        $unmatchedTotal = $total - $matchedTotal;
        $path = 'reports/race-control-discovery-'.now()->toDateString().'.md';

        Storage::disk('local')->put($path, $this->report($types, $reasons, $offences, $unmatched, $seasons, $total, $unmatchedTotal));

        $this->table(
            ['Type', 'Messages', ...$seasons],
            [
                ...array_map(fn (string $label, array $type) => [$label, $type['count'], ...array_map(fn (int $year) => $type['seasons'][$year] ?? 0, $seasons)], array_keys($types), $types),
                ['<comment>Unmatched</comment>', $unmatchedTotal, ...array_map(fn (int $year) => '', $seasons)],
            ],
        );
        $this->components->info(sprintf('%s messages, %s matched to %d types, %s unmatched in %d wordings. Report: %s',
            number_format($total), number_format($matchedTotal), count($types), number_format($unmatchedTotal), count($unmatched), Storage::disk('local')->path($path)));

        return self::SUCCESS;
    }

    private function sessionGroup(string $type): string
    {
        $type = SessionType::from($type);

        return match (true) {
            $type->isPractice() => 'practice',
            $type === SessionType::Qualifying, $type === SessionType::SprintQualifying => 'qualifying',
            $type === SessionType::Sprint => 'sprint',
            default => 'race',
        };
    }

    /**
     * Keep the first message of each wording (up to three) and of each season,
     * so the examples show different wordings, or else different seasons.
     *
     * @param  array{wordings: array<string, string>, seasons: array<int, string>}  $examples
     */
    private function collectExample(array &$examples, string $text, string $template, int $year): void
    {
        if (count($examples['wordings']) < self::EXAMPLES) {
            $examples['wordings'][$template] ??= $text;
        }

        $examples['seasons'][$year] ??= $text;
    }

    /**
     * @param  array{wordings: array<string, string>, seasons: array<int, string>}  $examples
     * @return list<string>
     */
    private function examplesFrom(array $examples): array
    {
        $seasons = $examples['seasons'];
        krsort($seasons);

        return array_slice(array_values(array_unique([...array_values($examples['wordings']), ...array_values($seasons)])), 0, self::EXAMPLES);
    }

    /**
     * @param  array<string, array{type: MessageType, count: int, seasons: array<int, int>, groups: array<string, int>, examples: array{wordings: array<string, string>, seasons: array<int, string>}}>  $types
     * @param  array<string, array{offence: string, count: int}>  $reasons
     * @param  array<string, array<string, int>>  $offences  Counts per offence label: the total and per type value
     * @param  array<string, array{count: int, seasons: array<int, true>, example: string}>  $unmatched
     * @param  list<int>  $seasons
     */
    private function report(array $types, array $reasons, array $offences, array $unmatched, array $seasons, int $total, int $unmatchedTotal): string
    {
        $cell = fn (string $text) => str_replace('|', '\|', $text);
        $lines = [
            '# Race control discovery',
            '',
            'Generated '.now()->toDateTimeString().' UTC from `race_control_messages` (OpenF1). '
                .number_format($total).' messages in '.implode(', ', $seasons).', '
                .number_format($total - $unmatchedTotal).' matched to '.count($types).' types, '
                .number_format($unmatchedTotal).' unmatched.',
            '',
            '## Types',
            '',
            '| Type | Total | '.implode(' | ', $seasons).' | Practice | Qualifying | Sprint | Race |',
            '|---|---:|'.str_repeat('---:|', count($seasons) + 4),
        ];

        foreach ($types as $label => $type) {
            $lines[] = "| {$cell($label)} | {$type['count']} | "
                .implode(' | ', array_map(fn (int $year) => $type['seasons'][$year] ?? 0, $seasons)).' | '
                .implode(' | ', array_map(fn (string $group) => $type['groups'][$group] ?? 0, ['practice', 'qualifying', 'sprint', 'race'])).' |';
        }

        $lines[] = '';
        $lines[] = '## Examples per type';

        foreach ($types as $label => $type) {
            $lines[] = '';
            $lines[] = "### {$label} ({$type['count']})";
            $lines[] = '';

            foreach ($this->examplesFrom($type['examples']) as $example) {
                $lines[] = "- `{$example}`";
            }
        }

        $lines[] = '';
        $lines[] = '## Offences by stage';
        $lines[] = '';
        $lines[] = '| Offence | Total | '.implode(' | ', array_map(fn (MessageType $stage) => $stage->label(), self::STAGES)).' |';
        $lines[] = '|---|---:|'.str_repeat('---:|', count(self::STAGES));

        foreach ($offences as $offence => $counts) {
            $lines[] = "| {$cell($offence)} | {$counts['total']} | ".implode(' | ', array_map(fn (MessageType $stage) => $counts[$stage->value] ?? 0, self::STAGES)).' |';
        }

        $lines[] = '';
        $lines[] = '## Offences named in stewards\' messages';
        $lines[] = '';
        $lines[] = '| Wording | Offence | Messages |';
        $lines[] = '|---|---|---:|';

        foreach ($reasons as $reason => $group) {
            $lines[] = "| {$cell($reason)} | {$cell($group['offence'])} | {$group['count']} |";
        }

        $lines[] = '';
        $lines[] = '## Unmatched messages by wording';
        $lines[] = '';
        $lines[] = 'Cars, times and numbers are replaced by `<CAR>`, `<TIME>` and `<N>`. '
            .count($unmatched).' wordings; the '.min(count($unmatched), (int) $this->option('unmatched')).' most common are listed.';
        $lines[] = '';
        $lines[] = '| Messages | Seasons | Wording | Example |';
        $lines[] = '|---:|---|---|---|';

        foreach (array_slice($unmatched, 0, (int) $this->option('unmatched'), true) as $template => $group) {
            $years = array_keys($group['seasons']);
            sort($years);
            $lines[] = "| {$group['count']} | ".implode(', ', $years)." | `{$cell($template)}` | {$cell($group['example'])} |";
        }

        return implode("\n", $lines)."\n";
    }
}
