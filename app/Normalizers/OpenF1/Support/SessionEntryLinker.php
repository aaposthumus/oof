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
    }
}
