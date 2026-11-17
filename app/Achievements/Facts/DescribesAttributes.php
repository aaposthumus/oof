<?php

namespace App\Achievements\Facts;

/**
 * A fact provider with attributes whose data starts later than the fact
 * itself (Jolpica's fastest lap rank from 2004) or needs a caveat. A
 * trigger whose conditions read such an attribute takes its year and note.
 */
interface DescribesAttributes
{
    /**
     * @return array<string, int> First year per attribute
     */
    public function attributeYears(): array;

    /**
     * @return array<string, string> Caveat per attribute
     */
    public function attributeNotes(): array;
}
