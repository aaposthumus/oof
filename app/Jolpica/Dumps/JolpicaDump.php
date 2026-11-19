<?php

namespace App\Jolpica\Dumps;

use Generator;
use RuntimeException;
use ZipArchive;

/**
 * A Jolpica CSV database dump: a zip with one file per table, such as
 * formula_one_lap.csv. Rows are read one at a time, straight from the zip.
 */
class JolpicaDump
{
    private const TABLE_PREFIX = 'formula_one_';

    public function __construct(public readonly string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("No Jolpica dump at [{$path}].");
        }
    }

    /**
     * @return Generator<int, array<string, string>> Each row keyed by column name
     */
    public function rows(string $table): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($this->path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("The Jolpica dump [{$this->path}] is not a readable zip.");
        }

        try {
            $stream = $zip->getStream($this->entryName($zip, $table));

            if ($stream === false) {
                throw new RuntimeException("The Jolpica dump has no readable table [{$table}].");
            }

            $columns = fgetcsv($stream, escape: '');

            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                yield array_combine($columns, $values);
            }

            fclose($stream);
        } finally {
            $zip->close();
        }
    }

    /**
     * The zip entry of a table, at the root or in a folder.
     */
    private function entryName(ZipArchive $zip, string $table): string
    {
        $file = self::TABLE_PREFIX.$table.'.csv';

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if ($name === $file || str_ends_with($name, '/'.$file)) {
                return $name;
            }
        }

        throw new RuntimeException("The Jolpica dump has no table [{$table}].");
    }
}
