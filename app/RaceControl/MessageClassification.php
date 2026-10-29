<?php

namespace App\RaceControl;

final readonly class MessageClassification
{
    /**
     * @param  string|null  $detail  The size or variant, e.g. "5 s", "3 places", "enabled"
     * @param  string|null  $reason  The offence as worded, e.g. "CAUSING A COLLISION"
     * @param  Offence|null  $offence  The offence the reason names, when it is a known one
     */
    public function __construct(
        public MessageType $type,
        public ?string $detail = null,
        public ?string $reason = null,
        public ?Offence $offence = null,
    ) {}

    public function label(): string
    {
        return $this->detail === null ? $this->type->label() : "{$this->type->label()}: {$this->detail}";
    }
}
