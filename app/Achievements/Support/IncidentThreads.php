<?php

namespace App\Achievements\Support;

use App\RaceControl\MessageType;
use Illuminate\Support\Collection;

/**
 * Chains a session's stewards' messages into incidents. A noted incident
 * starts a thread; later messages about the same offence join the latest
 * thread that shares a car with them, or start their own.
 */
class IncidentThreads
{
    /**
     * @param  Collection<int, ClassifiedMessage>  $messages  In the order they were issued
     * @return list<IncidentThread>
     */
    public static function build(Collection $messages): array
    {
        $threads = [];
        $stages = [MessageType::IncidentNoted, ...IncidentThread::INVESTIGATIONS, ...IncidentThread::OUTCOMES, MessageType::PenaltyServed];

        foreach ($messages as $message) {
            if (! $message->is(...$stages) || $message->entryIds === []) {
                continue;
            }

            $thread = $message->is(MessageType::IncidentNoted) ? null : self::latestFor($threads, $message);

            if ($thread === null) {
                $thread = new IncidentThread($message->classification->reason, $message->offence());
                $threads[] = $thread;
            }

            $thread->add($message);
        }

        return $threads;
    }

    /**
     * @param  list<IncidentThread>  $threads
     */
    private static function latestFor(array $threads, ClassifiedMessage $message): ?IncidentThread
    {
        for ($index = count($threads) - 1; $index >= 0; $index--) {
            if ($threads[$index]->involves($message) && $threads[$index]->isAbout($message)) {
                return $threads[$index];
            }
        }

        return null;
    }
}
