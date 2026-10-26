<?php

namespace App\Normalizers\Jolpica;

use App\Normalizers\Jolpica\Support\CoreResolver;
use App\Normalizers\Normalizer;

class SeasonsNormalizer implements Normalizer
{
    public function __construct(private CoreResolver $core) {}

    public function endpoint(): string
    {
        return 'seasons';
    }

    public function normalize(array $payload): void
    {
        foreach ($payload['MRData']['SeasonTable']['Seasons'] as $season) {
            $this->core->season((int) $season['season'], $season['url'] ?? null);
        }
    }
}
