<?php

namespace App\Normalizers\OpenF1;

use App\Normalizers\Normalizer;
use App\Normalizers\OpenF1\Support\OpenF1Resolver;
use App\Normalizers\OpenF1\Support\SessionContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for the per-session OpenF1 endpoints: maps a session's rows to one
 * table and makes that table hold exactly those rows for the session.
 */
abstract class SessionDataNormalizer implements Normalizer
{
    public function __construct(protected OpenF1Resolver $openF1) {}

    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Columns that identify a row within its session.
     *
     * @return list<string>
     */
    abstract protected function uniqueBy(): array;

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return list<array<string, mixed>> Rows for the table, without race_session_id
     */
    abstract protected function rows(array $payload, SessionContext $context): array;

    public function normalize(array $payload): void
    {
        if ($payload === []) {
            return;
        }

        $context = $this->openF1->sessionContext($payload);
        $sessionId = $context->session->id;
        $rows = array_map(fn (array $row) => ['race_session_id' => $sessionId, ...$row], $this->rows($payload, $context));
        $uniqueBy = ['race_session_id', ...$this->uniqueBy()];

        if ($rows === []) {
            return;
        }

        $model = $this->model();
        $update = array_values(array_diff(array_keys($rows[0]), $uniqueBy));

        foreach (array_chunk($rows, 500) as $chunk) {
            $model::upsert($chunk, $uniqueBy, $update);
        }

        $this->deleteRowsNotIn($model, $sessionId, $rows);
        $this->stored($context);
    }

    /**
     * Called once the session holds exactly the rows OpenF1 lists.
     */
    protected function stored(SessionContext $context): void {}

    /**
     * Remove the session's rows that OpenF1 no longer lists.
     *
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function deleteRowsNotIn(string $model, int $sessionId, array $rows): void
    {
        $keep = array_flip(array_map(fn (array $row) => $this->key($row), $rows));

        $stale = $model::query()
            ->where('race_session_id', $sessionId)
            ->toBase()
            ->get(['id', ...$this->uniqueBy()])
            ->reject(fn (object $row) => isset($keep[$this->key((array) $row)]))
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            $model::whereKey($stale->all())->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function key(array $row): string
    {
        return implode('|', array_map(fn (string $column) => (string) $row[$column], $this->uniqueBy()));
    }
}
