<?php

namespace App\Achievements;

use App\Enums\RecipientType;

/**
 * Who a detection is for: a driver (by the car they drove, which may be
 * unknown), a team (by its season team) or the Safety Car.
 */
final readonly class Recipient
{
    private function __construct(
        public RecipientType $type,
        public ?int $entryId = null,
        public ?int $seasonTeamId = null,
    ) {}

    public static function driver(?int $entryId): self
    {
        return new self(RecipientType::Driver, entryId: $entryId);
    }

    public static function team(int $seasonTeamId): self
    {
        return new self(RecipientType::Team, seasonTeamId: $seasonTeamId);
    }

    public static function safetyCar(): self
    {
        return new self(RecipientType::SafetyCar);
    }

    /**
     * What tells recipients of one type apart within a context: "e12" for
     * the car with entry 12, "t5" for season team 5; null when there is
     * only one (the Safety Car) or the car is not known.
     */
    public function key(): ?string
    {
        return match ($this->type) {
            RecipientType::Driver => $this->entryId === null ? null : "e{$this->entryId}",
            RecipientType::Team => "t{$this->seasonTeamId}",
            RecipientType::SafetyCar => null,
        };
    }

    public function is(RecipientType $type): bool
    {
        return $this->type === $type;
    }
}
