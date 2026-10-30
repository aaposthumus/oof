<?php

namespace App\Achievements\Support;

use App\Achievements\SessionData;
use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * A session's lap times deleted for track limits, one per car and time:
 * race control re-issues some deletions for the same lap.
 */
class TrackLimitDeletions
{
    /**
     * @return Collection<int, array{entry_id: int, time: string|null, message: ClassifiedMessage}>
     */
    public static function of(SessionData $data): Collection
    {
        return $data->messagesOfType(MessageType::TrackLimitsDeletion)
            ->filter(fn (ClassifiedMessage $message) => $message->soleEntryId() !== null)
            ->map(fn (ClassifiedMessage $message) => [
                'entry_id' => $message->soleEntryId(),
                'time' => self::deletedTime($message->message->message),
                'message' => $message,
            ])
            ->unique(fn (array $deletion) => "{$deletion['entry_id']}|".($deletion['time'] ?? $deletion['message']->message->lap_number ?? $deletion['message']->message->id))
            ->values();
    }

    /**
     * The deleted time as worded ("1:32.408"), if the message gives one.
     */
    public static function deletedTime(string $message): ?string
    {
        return preg_match('/TIME (\d+:\d{2}\.\d{3})/', $message, $matches) === 1 ? $matches[1] : null;
    }

    public static function toMillis(string $time): int
    {
        [$minutes, $seconds] = explode(':', $time);

        return (int) round(((int) $minutes * 60 + (float) $seconds) * 1000);
    }
}
