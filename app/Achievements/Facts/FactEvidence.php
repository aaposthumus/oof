<?php

namespace App\Achievements\Facts;

use App\Achievements\Support\ClassifiedMessage;
use App\Achievements\Support\Evidence;
use App\Models\DriverEntry;
use InvalidArgumentException;

/**
 * A fact's evidence before it is rendered for an award: race control
 * messages, result entries or rows. Evidence of the same kind merges, so a
 * count over several facts shows all of them.
 */
final readonly class FactEvidence
{
    /**
     * @param  'messages'|'results'|'rows'  $kind
     * @param  list<mixed>  $items
     */
    private function __construct(
        public string $kind,
        public array $items,
        public string $source,
        public ?string $note = null,
    ) {}

    /**
     * @param  iterable<ClassifiedMessage>  $messages
     */
    public static function messages(iterable $messages, ?string $note = null): self
    {
        return new self('messages', collect($messages)->values()->all(), 'race_control', $note);
    }

    /**
     * @param  iterable<DriverEntry>  $entries
     */
    public static function results(iterable $entries, ?string $note = null): self
    {
        return new self('results', collect($entries)->values()->all(), 'jolpica', $note);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function rows(string $source, array $rows, ?string $note = null): self
    {
        return new self('rows', $rows, $source, $note);
    }

    /**
     * @param  iterable<self>  $evidence
     */
    public static function merge(iterable $evidence): self
    {
        $all = collect($evidence)->values();
        $first = $all->first() ?? throw new InvalidArgumentException('Nothing to merge.');

        return new self($first->kind, $all->flatMap(fn (self $item) => $item->items)->all(), $first->source, $first->note);
    }

    public function withNote(?string $note): self
    {
        return new self($this->kind, $this->items, $this->source, $note);
    }

    /**
     * @return array{source: string, messages?: list<array<string, mixed>>, rows?: list<array<string, mixed>>, note?: string}
     */
    public function render(): array
    {
        return match ($this->kind) {
            'messages' => Evidence::messages($this->items, $this->note),
            'results' => Evidence::results($this->items, $this->note),
            'rows' => Evidence::rows($this->source, $this->items, $this->note),
        };
    }
}
