<?php

namespace App\Normalizers;

/**
 * Translates one raw response payload into core tables. Implementations must be
 * idempotent: normalizing the same payload twice leaves the same rows.
 */
interface Normalizer
{
    /**
     * The raw_responses endpoint this normalizer handles.
     */
    public function endpoint(): string;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws MissingDependencyException when rows it relies on are not normalized yet
     */
    public function normalize(array $payload): void;
}
