<?php

namespace App\RaceControl;

final readonly class MessageClassification
{
    /**
     * @param  string|null  $detail  The size or variant, e.g. "5 s", "3 places", "enabled"
     * @param  string|null  $reason  The offence, e.g. "CAUSING A COLLISION"
     */
    public function __construct(
        public MessageType $type,
        public ?string $detail = null,
        public ?string $reason = null,
    ) {}

    public function label(): string
    {
        return $this->detail === null ? $this->type->label() : "{$this->type->label()}: {$this->detail}";
    }
}
