<?php

namespace App\Achievements\Support;

use App\RaceControl\MessageType;
use App\RaceControl\Offence;

/**
 * One incident as the stewards' messages tell it: noted, investigated, then
 * an outcome per car (no further action, a warning, a penalty) and perhaps
 * the penalty served.
 */
class IncidentThread
{
    public const INVESTIGATIONS = [MessageType::UnderInvestigation, MessageType::InvestigatedAfterSession];

    public const PENALTIES = [
        MessageType::TimePenalty, MessageType::DriveThroughPenalty, MessageType::StopGoPenalty,
        MessageType::GridPenalty, MessageType::PitLaneStart, MessageType::Disqualification,
    ];

    public const OUTCOMES = [
        MessageType::NoFurtherAction, MessageType::Warning, MessageType::Reprimand, MessageType::BlackAndWhiteFlag,
        ...self::PENALTIES,
    ];

    /**
     * @var list<ClassifiedMessage>
     */
    public array $messages = [];

    /**
     * @var array<int, true>
     */
    private array $entryIds = [];

    public function __construct(public readonly ?string $reason, public readonly ?Offence $offence) {}

    public function add(ClassifiedMessage $message): void
    {
        $this->messages[] = $message;

        foreach ($message->entryIds as $entryId) {
            $this->entryIds[$entryId] = true;
        }
    }

    public function id(): int
    {
        return $this->messages[0]->message->id;
    }

    public function eventKey(): string
    {
        return "incident:{$this->id()}";
    }

    /**
     * @return list<int>
     */
    public function entryIds(): array
    {
        return array_keys($this->entryIds);
    }

    public function involves(ClassifiedMessage $message): bool
    {
        return array_intersect($message->entryIds, $this->entryIds()) !== [];
    }

    public function isAbout(ClassifiedMessage $message): bool
    {
        $offence = $message->offence();

        return $message->classification->reason === $this->reason || ($offence !== null && $offence === $this->offence);
    }

    /**
     * Under investigation without an outcome yet.
     */
    public function isOpen(): bool
    {
        $open = false;

        foreach ($this->messages as $message) {
            if ($message->is(...self::INVESTIGATIONS)) {
                $open = true;
            } elseif ($message->is(...self::OUTCOMES)) {
                $open = false;
            }
        }

        return $open;
    }

    /**
     * The penalty messages, each naming the one car it penalises.
     *
     * @return list<ClassifiedMessage>
     */
    public function penalties(): array
    {
        return array_values(array_filter($this->messages, fn (ClassifiedMessage $message) => $message->is(...self::PENALTIES)));
    }

    /**
     * The messages that tell this entry's part, from the noting up to its own outcome.
     *
     * @return list<ClassifiedMessage>
     */
    public function messagesFor(int $entryId): array
    {
        return array_values(array_filter(
            $this->messages,
            fn (ClassifiedMessage $message) => in_array($entryId, $message->entryIds, true) || ! $message->is(MessageType::PenaltyServed, ...self::OUTCOMES),
        ));
    }
}
