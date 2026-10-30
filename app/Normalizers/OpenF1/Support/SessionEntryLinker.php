<?php

namespace App\Normalizers\OpenF1\Support;

use App\Models\RaceSession;
use Illuminate\Support\Facades\DB;

/**
 * Points a session's OpenF1 rows at the driver entries their cars are
 * matched to, so rows stored while a car was unmatched get linked once it is.
 */
class SessionEntryLinker
{
    /**
     * Tables with their column holding the car number.
     */
    private const TABLES = [
        'race_control_messages' => 'car_number',
        'stints' => 'driver_number',
        'session_laps' => 'driver_number',
        'pit_lane_visits' => 'driver_number',
        'team_radio_messages' => 'driver_number',
        'positions' => 'driver_number',
    ];

    public function relink(RaceSession $session): void
    {
        foreach (self::TABLES as $table => $carColumn) {
            DB::table($table)
                ->where('race_session_id', $session->id)
                ->update(['driver_entry_id' => DB::raw(
                    "(select driver_entry_id from openf1_session_drivers where openf1_session_drivers.race_session_id = {$table}.race_session_id and openf1_session_drivers.driver_number = {$table}.{$carColumn})",
                )]);
        }

        $this->linkMessageEntries($session);
    }

    /**
     * Link race control messages to every driver entry they name, so a
     * message about several cars ("INCIDENT INVOLVING CARS 87 (BEA) AND
     * 10 (GAS) NOTED") reaches each of them. Without a session, all
     * sessions are linked.
     */
    public function linkMessageEntries(?RaceSession $session = null): void
    {
        $sessionFilter = $session === null ? '' : 'where m.race_session_id = ?';
        $bindings = $session === null ? [] : [$session->id];

        DB::delete(
            'delete link from race_control_message_driver_entry link join race_control_messages m on m.id = link.race_control_message_id '.$sessionFilter,
            $bindings,
        );

        DB::insert(<<<SQL
            insert into race_control_message_driver_entry (race_control_message_id, driver_entry_id, car_number)
            select distinct m.id, d.driver_entry_id, d.driver_number
            from race_control_messages m
            join openf1_session_drivers d on d.race_session_id = m.race_session_id and d.driver_entry_id is not null
                and (d.driver_number = m.car_number or json_contains(coalesce(m.car_numbers, json_array()), cast(d.driver_number as json)))
            {$sessionFilter}
            SQL, $bindings);
    }
}
