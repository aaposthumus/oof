<?php

namespace App\Filament\Resources\Rounds\Schemas;

use App\Achievements\Support\Finish;
use App\Enums\SessionType;
use App\Filament\Resources\AchievementAwards\AchievementAwardResource;
use App\Models\AchievementAward;
use App\Models\DriverEntry;
use App\Models\RaceControlMessage;
use App\Models\RaceSession;
use App\Models\Result;
use App\Models\Round;
use App\Models\SessionLap;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

/**
 * The round page: its details, one tab per session with the classification
 * and the race control messages, and a tab with the awards.
 */
class RoundInfolist
{
    /**
     * The order of the session tabs: the weekend as it is run.
     *
     * @var list<SessionType>
     */
    private const ORDER = [
        SessionType::Practice1, SessionType::SprintQualifying, SessionType::Sprint,
        SessionType::Practice2, SessionType::Practice3, SessionType::Qualifying, SessionType::Race,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Round')
                ->columns(5)
                ->schema([
                    TextEntry::make('season.year')->label('Season'),
                    TextEntry::make('round_number')->label('Round'),
                    TextEntry::make('name'),
                    TextEntry::make('circuit.name')->label('Circuit')->placeholder('–'),
                    TextEntry::make('date')->date(),
                ]),
            Tabs::make('Sessions')
                ->persistTabInQueryString()
                ->tabs(fn (Round $record): array => [
                    ...self::sessions($record)->map(fn (RaceSession $session) => Tab::make($session->type->label())
                        ->id($session->type->value)
                        ->schema([View::make('filament.rounds.session')->viewData(self::sessionData($session))]))->all(),
                    Tab::make('Awards')
                        ->id('awards')
                        ->schema([View::make('filament.rounds.awards')->viewData(['awards' => self::awards($record)])]),
                ]),
        ]);
    }

    /**
     * @return Collection<int, RaceSession>
     */
    private static function sessions(Round $round): Collection
    {
        return $round->raceSessions()->get()
            ->sortBy(fn (RaceSession $session) => array_search($session->type, self::ORDER, true))
            ->values();
    }

    /**
     * @return array{type: SessionType, heading: string, columns: list<string>, rows: list<list<string>>, messages: list<array{time: string, lap: string, flag: string, text: string}>}
     */
    private static function sessionData(RaceSession $session): array
    {
        [$heading, $columns, $rows] = match (true) {
            in_array($session->type, [SessionType::Race, SessionType::Sprint], true) => ['Results', ...self::raceRows($session)],
            in_array($session->type, [SessionType::Qualifying, SessionType::SprintQualifying], true) => ['Qualifying', ...self::qualifyingRows($session)],
            default => ['Classification', ...self::practiceRows($session)],
        };

        return [
            'type' => $session->type,
            'heading' => $heading,
            'columns' => $columns,
            'rows' => $rows,
            'messages' => RaceControlMessage::query()
                ->where('race_session_id', $session->id)
                ->orderBy('issued_at')
                ->orderBy('id')
                ->get()
                ->map(fn (RaceControlMessage $message) => [
                    'time' => $message->issued_at->format('H:i:s'),
                    'lap' => (string) ($message->lap_number ?? ''),
                    'flag' => (string) ($message->flag ?? $message->category ?? ''),
                    'text' => $message->message,
                ])->all(),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private static function raceRows(RaceSession $session): array
    {
        $entries = self::entries($session, ['result'])->filter(fn (DriverEntry $entry) => $entry->result !== null)
            ->sortBy(fn (DriverEntry $entry) => $entry->result->classification_order ?? PHP_INT_MAX);

        return [
            ['Pos', 'Driver', 'Team', 'Grid', 'Laps', 'Status', 'Time / gap', 'Pts', 'Fastest lap'],
            $entries->map(fn (DriverEntry $entry) => [
                $entry->result->position_text ?? (string) $entry->result->position,
                self::driver($entry),
                $entry->seasonTeam->name,
                (string) ($entry->result->grid === 0 ? 'Pit lane' : $entry->result->grid),
                (string) $entry->result->laps_completed,
                self::status($entry->result),
                (string) $entry->result->time_text,
                rtrim(rtrim((string) $entry->result->points, '0'), '.') ?: '0',
                self::fastestLap($entry->result),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private static function qualifyingRows(RaceSession $session): array
    {
        $entries = self::entries($session, ['qualifyingResult'])->filter(fn (DriverEntry $entry) => $entry->qualifyingResult !== null)
            ->sortBy(fn (DriverEntry $entry) => $entry->qualifyingResult->position ?? PHP_INT_MAX);

        return [
            ['Pos', 'Driver', 'Team', 'Q1', 'Q2', 'Q3'],
            $entries->map(fn (DriverEntry $entry) => [
                (string) $entry->qualifyingResult->position,
                self::driver($entry),
                $entry->seasonTeam->name,
                self::time($entry->qualifyingResult->q1_millis),
                self::time($entry->qualifyingResult->q2_millis),
                self::time($entry->qualifyingResult->q3_millis),
            ])->values()->all(),
        ];
    }

    /**
     * Practice has no classification of its own: it is each car's best lap
     * and number of laps from OpenF1.
     *
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private static function practiceRows(RaceSession $session): array
    {
        $laps = SessionLap::query()->where('race_session_id', $session->id)->whereNotNull('driver_entry_id')->get()->groupBy('driver_entry_id');
        $entries = self::entries($session)->filter(fn (DriverEntry $entry) => $laps->has($entry->id))
            ->sortBy(fn (DriverEntry $entry) => $laps[$entry->id]->whereNotNull('duration_millis')->min('duration_millis') ?? PHP_INT_MAX)
            ->values();
        $best = $entries->first() === null ? null : $laps[$entries->first()->id]->whereNotNull('duration_millis')->min('duration_millis');

        return [
            ['Pos', 'Driver', 'Team', 'Best lap', 'Gap', 'Laps'],
            $entries->map(function (DriverEntry $entry, int $index) use ($laps, $best) {
                $time = $laps[$entry->id]->whereNotNull('duration_millis')->min('duration_millis');

                return [
                    (string) ($index + 1),
                    self::driver($entry),
                    $entry->seasonTeam->name,
                    self::time($time),
                    $time === null || $best === null || $time === $best ? '' : '+'.number_format(($time - $best) / 1000, 3),
                    (string) $laps[$entry->id]->count(),
                ];
            })->all(),
        ];
    }

    /**
     * @param  list<string>  $with
     * @return Collection<int, DriverEntry>
     */
    private static function entries(RaceSession $session, array $with = []): Collection
    {
        return $session->driverEntries()->with(['driver', 'seasonTeam', ...$with])->get();
    }

    private static function driver(DriverEntry $entry): string
    {
        return "#{$entry->car_number} {$entry->driver->given_name} {$entry->driver->family_name}";
    }

    /**
     * The DNF reason, DNS or DSQ as Jolpica words it; a finisher shows its status.
     */
    private static function status(Result $result): string
    {
        return match (true) {
            Finish::isDisqualified($result) => "DSQ · {$result->status}",
            Finish::didNotStart($result) => "DNS · {$result->status}",
            default => $result->status,
        };
    }

    private static function fastestLap(Result $result): string
    {
        if ($result->fastest_lap_time_millis === null) {
            return '';
        }

        return self::time($result->fastest_lap_time_millis).($result->fastest_lap_rank === 1 ? ' (fastest)' : '');
    }

    private static function time(?int $millis): string
    {
        if ($millis === null) {
            return '';
        }

        $minutes = intdiv($millis, 60_000);
        $seconds = ($millis % 60_000) / 1000;

        return $minutes > 0 ? sprintf('%d:%06.3f', $minutes, $seconds) : sprintf('%.3f', $seconds);
    }

    /**
     * @return list<array{session: string, driver: string, title: string, description: string|null, status: string, url: string}>
     */
    private static function awards(Round $round): array
    {
        return AchievementAward::query()
            ->whereIn('race_session_id', $round->raceSessions()->select('id'))
            ->with(['raceSession', 'driver', 'achievement'])
            ->orderBy('race_session_id')
            ->orderBy('id')
            ->get()
            ->map(fn (AchievementAward $award) => [
                'session' => $award->raceSession->type->label(),
                'driver' => $award->driver === null ? '' : "{$award->driver->given_name} {$award->driver->family_name}",
                'title' => $award->title(),
                'description' => $award->descriptionLine(),
                'status' => $award->status->label(),
                'url' => AchievementAwardResource::getUrl('view', ['record' => $award]),
            ])->all();
    }
}
