<?php

namespace App\Achievements\Triggers;

use App\Achievements\Detector;
use App\Achievements\LateDetector;
use App\Achievements\SessionData;
use Illuminate\Support\Collection;

/**
 * A trigger of type custom: a class, for logic that does not fit the
 * declarative types.
 */
final readonly class CustomTrigger implements Trigger
{
    public function __construct(private Detector|LateDetector $detector) {}

    public function isLate(): bool
    {
        return $this->detector instanceof LateDetector;
    }

    public function detect(SessionData $data, Collection $settled): Collection
    {
        return $this->detector instanceof LateDetector
            ? $this->detector->detectAfter($data, $settled)
            : $this->detector->detect($data);
    }
}
