<?php

namespace App\Normalizers\Jolpica\Support;

use Illuminate\Support\Facades\Log;

/**
 * Derives a round's official name, short name and country from the race name
 * Jolpica gives, never from the circuit.
 */
class RoundNameResolver
{
    /**
     * @param  array<string, array{short_name: string, country_name: ?string, country_code: ?string}>  $grandPrixNames
     * @param  array<string, array{name: string, short_name: string, country_name: ?string, country_code: ?string}>  $overrides
     */
    public function __construct(
        private array $grandPrixNames,
        private array $overrides,
    ) {}

    /**
     * @return array{name: string, short_name: string, country_name: ?string, country_code: ?string}
     */
    public function resolve(string $raceName, int $year, int $round): array
    {
        if (isset($this->overrides["{$year}-{$round}"])) {
            return $this->overrides["{$year}-{$round}"];
        }

        // "Bahrain Grand Prix in Malaysia" is the Bahrain Grand Prix held elsewhere.
        $candidates = [$raceName, preg_replace('/ in [^,]+$/u', '', $raceName)];

        foreach ($candidates as $name) {
            if (isset($this->grandPrixNames[$name])) {
                return ['name' => $name, ...$this->grandPrixNames[$name]];
            }
        }

        Log::warning('No grand prix name mapping; add it to config/f1.php.', compact('raceName', 'year', 'round'));

        return [
            'name' => $raceName,
            'short_name' => trim(str_replace('Grand Prix', '', $raceName)),
            'country_name' => null,
            'country_code' => null,
        ];
    }
}
