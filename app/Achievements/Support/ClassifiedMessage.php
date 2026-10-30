<?php

namespace App\Achievements\Support;

use App\Models\RaceControlMessage;
use App\RaceControl\MessageClassification;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;

/**
 * A race control message with its type and every driver entry it names.
 */
final readonly class ClassifiedMessage
{
    /**
     * @param  list<int>  $entryIds
     * @param  int|null  $phase  The qualifying phase it falls in; OpenF1 leaves it out on many messages
     */
    public function __construct(
        public RaceControlMessage $message,
        public ?MessageClassification $classification,
        public array $entryIds,
        public ?int $phase = null,
    ) {}

    public function type(): ?MessageType
    {
        return $this->classification?->type;
    }

    public function is(MessageType ...$types): bool
    {
        return in_array($this->type(), $types, true);
    }

    public function offence(): ?Offence
    {
        return $this->classification?->offence;
    }

    /**
     * The one entry the message is about, if it names exactly one.
     */
    public function soleEntryId(): ?int
    {
        return count($this->entryIds) === 1 ? $this->entryIds[0] : null;
    }

    public function carsNamed(): int
    {
        return count($this->message->car_numbers ?? ($this->message->car_number === null ? [] : [$this->message->car_number]));
    }
}
