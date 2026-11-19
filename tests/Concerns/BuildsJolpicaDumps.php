<?php

namespace Tests\Concerns;

use App\Jolpica\Dumps\JolpicaDump;
use ZipArchive;

trait BuildsJolpicaDumps
{
    /**
     * Write a small Jolpica CSV dump (one formula_one_{table}.csv per table,
     * columns from the first row) to a temporary zip.
     *
     * @param  array<string, list<array<string, int|string>>>  $tables
     */
    protected function jolpicaDump(array $tables): JolpicaDump
    {
        return new JolpicaDump($this->jolpicaDumpZip($tables));
    }

    /**
     * @param  array<string, list<array<string, int|string>>>  $tables
     */
    protected function jolpicaDumpZip(array $tables): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jolpica-dump-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($tables as $table => $rows) {
            $csv = fopen('php://memory', 'w+');
            fputcsv($csv, array_keys($rows[0]), escape: '');

            foreach ($rows as $row) {
                fputcsv($csv, array_values($row), escape: '');
            }

            rewind($csv);
            $zip->addFromString("formula_one_{$table}.csv", stream_get_contents($csv));
            fclose($csv);
        }

        $zip->close();

        return $path;
    }
}
