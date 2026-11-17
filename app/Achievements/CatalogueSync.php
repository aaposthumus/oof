<?php

namespace App\Achievements;

use App\Achievements\Facts\DataSource;
use App\Achievements\Facts\Requirements;
use App\Models\Achievement;
use Illuminate\Support\Facades\DB;

/**
 * Brings the achievements table in line with the catalogue: creates
 * missing achievements, and writes the mechanics (sessions, stacks,
 * withheld on sensitive weekends) and the derived data needs over what the
 * row holds. Editorial fields are left to the CMS. Rows without a catalogue
 * entry are reported, never deleted.
 */
class CatalogueSync
{
    public function __construct(private Catalogue $catalogue, private Requirements $requirements) {}

    /**
     * What a sync would change.
     *
     * @return array{create: list<string>, update: list<array{key: string, field: string, from: mixed, to: mixed}>, unknown: list<string>}
     */
    public function plan(): array
    {
        $rows = Achievement::query()->get()->keyBy('key');
        $plan = ['create' => [], 'update' => [], 'unknown' => $rows->keys()->diff($this->catalogue->keys())->values()->all()];

        foreach ($this->catalogue->keys() as $key) {
            $row = $rows->get($key);

            if ($row === null) {
                $plan['create'][] = $key;

                continue;
            }

            foreach ($this->owned($key) as $field => $value) {
                if (! self::same($row->{$field}, $value)) {
                    $plan['update'][] = ['key' => $key, 'field' => $field, 'from' => $row->{$field}, 'to' => $value];
                }
            }
        }

        return $plan;
    }

    /**
     * Apply the plan in one transaction.
     *
     * @return array{create: list<string>, update: list<array{key: string, field: string, from: mixed, to: mixed}>, unknown: list<string>}
     */
    public function apply(): array
    {
        return DB::transaction(function () {
            $plan = $this->plan();

            foreach ($plan['create'] as $key) {
                Achievement::create(['key' => $key, ...$this->catalogue->editorial($key), ...$this->owned($key)]);
            }

            foreach (collect($plan['update'])->groupBy('key') as $key => $changes) {
                Achievement::query()->where('key', $key)->sole()->update($changes->pluck('to', 'field')->all());
            }

            return $plan;
        });
    }

    /**
     * The fields the catalogue owns, as the row should hold them.
     *
     * @return array<string, mixed>
     */
    public function owned(string $key): array
    {
        $needs = $this->requirements->for($key);

        return [
            ...$this->catalogue->mechanics($key),
            'data_sources' => array_map(fn (DataSource $source) => $source->value, $needs['sources']),
            'available_from' => $needs['available_from'],
        ];
    }

    /**
     * Lists compare as sets, so a reordering in the CMS is no difference.
     */
    private static function same(mixed $stored, mixed $wanted): bool
    {
        if (is_array($stored) && is_array($wanted)) {
            sort($stored);
            sort($wanted);
        }

        return $stored === $wanted;
    }
}
