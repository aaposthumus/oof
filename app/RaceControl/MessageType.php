<?php

namespace App\RaceControl;

/**
 * Kinds of race control message, in the order they are tried: the first
 * whose pattern matches wins, so specific kinds come before general ones
 * (a served penalty before a time penalty, a penalty before an incident).
 * A stewards' message is typed by its stage (noted, under investigation, no
 * further action, penalty); the offence it names is its Offence.
 */
enum MessageType: string
{
    case PenaltyServed = 'penalty_served';
    case TimePenalty = 'time_penalty';
    case StopGoPenalty = 'stop_go_penalty';
    case DriveThroughPenalty = 'drive_through_penalty';
    case GridPenalty = 'grid_penalty';
    case PitLaneStart = 'pit_lane_start';
    case Reprimand = 'reprimand';
    case Warning = 'warning';
    case Disqualification = 'disqualification';
    case BlackFlag = 'black_flag';
    case BlackAndOrangeFlag = 'black_and_orange_flag';
    case BlackAndWhiteFlag = 'black_and_white_flag';
    case NoFurtherAction = 'no_further_action';
    case InvestigatedAfterSession = 'investigated_after_session';
    case UnderInvestigation = 'under_investigation';
    case IncidentNoted = 'incident_noted';
    case TrackLimitsDeletion = 'track_limits_deletion';
    case TimeDeletedOther = 'time_deleted_other';
    case TimeReinstated = 'time_reinstated';
    case RedFlag = 'red_flag';
    case SafetyCarDeployed = 'safety_car_deployed';
    case SafetyCarInThisLap = 'safety_car_in_this_lap';
    case SafetyCarEnding = 'safety_car_ending';
    case SafetyCarProcedure = 'safety_car_procedure';
    case VirtualSafetyCarDeployed = 'vsc_deployed';
    case VirtualSafetyCarEnding = 'vsc_ending';
    case BlueFlag = 'blue_flag';
    case DoubleYellowFlag = 'double_yellow_flag';
    case YellowFlag = 'yellow_flag';
    case ClearFlag = 'clear_flag';
    case GreenFlag = 'green_flag';
    case ChequeredFlag = 'chequered_flag';
    case LappedCars = 'lapped_cars';
    case Drs = 'drs';
    case OvertakeMode = 'overtake_mode';
    case PitExit = 'pit_exit';
    case PitEntry = 'pit_entry';
    case PitLaneAccess = 'pit_lane_access';
    case SessionStatus = 'session_status';
    case StartProcedure = 'start_procedure';
    case FirstCarToTakeTheFlag = 'first_car_to_take_the_flag';
    case RiskOfRain = 'risk_of_rain';
    case WetConditions = 'wet_conditions';
    case GripConditions = 'grip_conditions';
    case SlipperySurface = 'slippery_surface';
    case Temperature = 'temperature';
    case MarshalsOnTrack = 'marshals_on_track';
    case OffTrack = 'off_track';
    case CarStopped = 'car_stopped';
    case MedicalCar = 'medical_car';
    case Notice = 'notice';

    public function label(): string
    {
        return match ($this) {
            self::PenaltyServed => 'Penalty served',
            self::TimePenalty => 'Time penalty',
            self::StopGoPenalty => 'Stop-and-go penalty',
            self::DriveThroughPenalty => 'Drive-through penalty',
            self::GridPenalty => 'Grid penalty',
            self::PitLaneStart => 'Pit lane start',
            self::Reprimand => 'Reprimand',
            self::Warning => 'Warning',
            self::Disqualification => 'Disqualification',
            self::BlackFlag => 'Black flag',
            self::BlackAndOrangeFlag => 'Black-and-orange flag',
            self::BlackAndWhiteFlag => 'Black-and-white flag',
            self::NoFurtherAction => 'No further action',
            self::InvestigatedAfterSession => 'Investigated after the session',
            self::UnderInvestigation => 'Under investigation',
            self::IncidentNoted => 'Incident noted',
            self::TrackLimitsDeletion => 'Track limits deletion',
            self::TimeDeletedOther => 'Time deleted (other reason)',
            self::TimeReinstated => 'Time reinstated',
            self::RedFlag => 'Red flag',
            self::SafetyCarDeployed => 'Safety car deployed',
            self::SafetyCarInThisLap => 'Safety car in this lap',
            self::SafetyCarEnding => 'Safety car ending',
            self::SafetyCarProcedure => 'Safety car procedure',
            self::VirtualSafetyCarDeployed => 'VSC deployed',
            self::VirtualSafetyCarEnding => 'VSC ending',
            self::BlueFlag => 'Blue flag',
            self::DoubleYellowFlag => 'Double yellow flag',
            self::YellowFlag => 'Yellow flag',
            self::ClearFlag => 'Clear',
            self::GreenFlag => 'Green flag',
            self::ChequeredFlag => 'Chequered flag',
            self::LappedCars => 'Lapped cars',
            self::Drs => 'DRS',
            self::OvertakeMode => 'Overtake mode',
            self::PitExit => 'Pit exit',
            self::PitEntry => 'Pit entry',
            self::PitLaneAccess => 'Pit lane access',
            self::SessionStatus => 'Session status',
            self::StartProcedure => 'Start procedure',
            self::FirstCarToTakeTheFlag => 'First car to take the flag',
            self::RiskOfRain => 'Risk of rain',
            self::WetConditions => 'Wet conditions',
            self::GripConditions => 'Grip conditions',
            self::SlipperySurface => 'Slippery surface',
            self::Temperature => 'Temperature',
            self::MarshalsOnTrack => 'Marshals on track',
            self::OffTrack => 'Off track',
            self::CarStopped => 'Car stopped',
            self::MedicalCar => 'Medical car',
            self::Notice => 'Notice',
        };
    }
}
