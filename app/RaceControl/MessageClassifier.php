<?php

namespace App\RaceControl;

/**
 * Sorts race control messages into types by their wording, category and flag.
 */
class MessageClassifier
{
    /**
     * Patterns per type, tried in order. A flag rule matches OpenF1's flag field;
     * the first capture group, if any, becomes the detail.
     *
     * @var list<array{0: MessageType, 1: string|null, 2?: string}> Type, message regex, flag
     */
    private const RULES = [
        [MessageType::PenaltyServed, '/PENALTY SERVED - (.+?) PENALTY/'],
        [MessageType::TimePenalty, '/(\d+) SECOND TIME PENALTY/'],
        [MessageType::StopGoPenalty, '/(\d+ SECOND )?STOP\/GO PENALTY|STOP[ -]AND[ -]GO PENALTY/'],
        [MessageType::DriveThroughPenalty, '/DRIVE[ -]THROUGH PENALTY/'],
        [MessageType::GridPenalty, '/(\d+) PLACE GRID PENALTY/'],
        [MessageType::PitLaneStart, '/START FROM THE PIT LANE|PIT LANE START/'],
        [MessageType::Reprimand, '/REPRIMAND(?: \(((?:NON-)?DRIVING)\))?/'],
        [MessageType::Warning, '/\bWARNING FOR CARS? /'],
        [MessageType::Disqualification, '/DISQUALIFIED/'],
        [MessageType::BlackAndOrangeFlag, '/BLACK AND ORANGE/', 'BLACK AND ORANGE'],
        [MessageType::BlackAndWhiteFlag, '/BLACK AND WHITE FLAG/', 'BLACK AND WHITE'],
        [MessageType::BlackFlag, '/\bBLACK FLAG\b/', 'BLACK'],
        [MessageType::NoFurtherAction, '/NO FURTHER ACTION|NO FURTHER INVESTIGATION|NO INVESTIGATION NECESSARY/'],
        [MessageType::InvestigatedAfterSession, '/WILL BE INVESTIGATED AFTER THE (?:SESSION|RACE|SPRINT)/'],
        [MessageType::UnderInvestigation, '/UNDER INVESTIGATION/'],
        [MessageType::IncidentNoted, '/INCIDENTS? .*NOTED|INCIDENTS? INVOLVING CARS? |^LAP \d+ TURN \d+ NOTED/'],
        [MessageType::TrackLimitsDeletion, '/DELETED - TRACK LIMITS/'],
        [MessageType::TimeDeletedOther, '/(?:TIME|LAP)\b.* DELETED/'],
        [MessageType::TimeReinstated, '/REINSTATED/'],
        [MessageType::RedFlag, '/^RED FLAG\b/', 'RED'],
        [MessageType::VirtualSafetyCarDeployed, '/(?:VIRTUAL SAFETY CAR|VSC) DEPLOYED/'],
        [MessageType::VirtualSafetyCarEnding, '/(?:VIRTUAL SAFETY CAR|VSC) ENDING/'],
        [MessageType::SafetyCarDeployed, '/^SAFETY CAR DEPLOYED/'],
        [MessageType::SafetyCarInThisLap, '/SAFETY CAR IN THIS LAP|SAFETY CAR WILL ENTER PITS/'],
        [MessageType::SafetyCarEnding, '/SAFETY CAR ENDING/'],
        [MessageType::StartProcedure, '/^(?:RACE|FORMATION LAP).* (BEHIND) (?:THE )?SAFETY CAR/'],
        [MessageType::LappedCars, '/LAPPED CARS?\b/'],
        [MessageType::SafetyCarProcedure, '/SAFETY CAR (LIGHTS (?:ON|OFF))/'],
        [MessageType::SafetyCarProcedure, '/(THROUGH THE PIT LANE|USE START\/FINISH STRAIGHT)|MAY OVERTAKE THE SAFETY CAR|SAFETY CAR WILL STAY OUT/'],
        [MessageType::BlueFlag, '/BLUE FLAG/', 'BLUE'],
        [MessageType::DoubleYellowFlag, '/DOUBLE YELLOW/', 'DOUBLE YELLOW'],
        [MessageType::YellowFlag, '/^YELLOW/', 'YELLOW'],
        [MessageType::ClearFlag, '/^(?:TRACK |PIT LANE )?CLEAR/', 'CLEAR'],
        [MessageType::GreenFlag, '/^GREEN/', 'GREEN'],
        [MessageType::ChequeredFlag, '/^CHEQUERED FLAG/', 'CHEQUERED'],
        [MessageType::Drs, '/DRS ((?:ENABLED|DISABLED)(?: IN ZONE)?)/'],
        [MessageType::OvertakeMode, '/OVERTAKE (ENABLED|DISABLED)/'],
        [MessageType::PitExit, '/PIT EXIT (OPEN|CLOSED)/'],
        [MessageType::PitEntry, '/PIT (?:LANE )?ENTRY (OPEN|CLOSED)/'],
        [MessageType::PitLaneAccess, '/PASS HOLDERS|PIT LANE (?:IS )?(OPEN|CLOSED)/'],
        [MessageType::SessionStatus, '/^(?:CORRECTION: )?(?:SESSION|Q\d|SQ\d|RACE|SPRINT|QUALIFYING|FREE PRACTICE \d) (STARTED|FINISHED|RESUMED|SUSPENDED|ABORTED|WILL (?:START|RESUME|NOT BE RESUMED|BE EXTENDED|BE TEMPORARILY STOPPED))/'],
        [MessageType::SessionStatus, '/^ESTIMATED TIME OF .*(START|RESUMPTION)/'],
        [MessageType::SessionStatus, '/(DELAYED)/'],
        [MessageType::StartProcedure, '/(RACE START|SPRINT START|FORMATION LAP|START ABORTED|ABORTED START|STARTING PROCEDURE SUSPENDED|RESUMPTION ORDER|STANDING START|ROLLING START|ORDER FOR RESTART|RESTART ORDER|START ORDER|PUSH CARS TO FRONT)/'],
        [MessageType::FirstCarToTakeTheFlag, '/FIRST CAR TO TAKE THE FLAG/'],
        [MessageType::RiskOfRain, '/RISK OF RAIN/'],
        [MessageType::WetConditions, '/(WET TRACK|CHANGE IN CLIMATIC CONDITIONS|INTERMEDIATE TYRES IS MANDATORY)/'],
        [MessageType::GripConditions, '/(LOW|NORMAL) GRIP/'],
        [MessageType::SlipperySurface, '/SLIPPERY/'],
        [MessageType::Temperature, '/TEMPERATURE/'],
        [MessageType::MarshalsOnTrack, '/MARSHALS ON TRACK/'],
        [MessageType::OffTrack, '/^(?:CORRECTION: )?CAR .*(OFF TRACK|SPUN|MISSED THE APEX)/'],
        [MessageType::CarStopped, '/\bSTOPPED\b|RECOVERY VEHICLE/'],
        [MessageType::MedicalCar, '/MEDICAL CAR/'],
        [MessageType::Notice, '/HEAD PADDING|AWNINGS|GARAGE DOORS|PARC FERME|WORKED ON|NO PRACTICE STARTS|TRACK TEST|WEATHER RADAR|TRANSPONDER|COVERS ON TIME|BRIEFING/'],
    ];

    /**
     * Types whose messages name an offence after a dash, e.g. "... NOTED - IMPEDING".
     */
    private const WITH_REASON = [
        MessageType::PenaltyServed, MessageType::TimePenalty, MessageType::StopGoPenalty, MessageType::DriveThroughPenalty,
        MessageType::GridPenalty, MessageType::PitLaneStart, MessageType::Reprimand, MessageType::Warning, MessageType::Disqualification,
        MessageType::BlackFlag, MessageType::BlackAndOrangeFlag, MessageType::BlackAndWhiteFlag, MessageType::NoFurtherAction, MessageType::InvestigatedAfterSession,
        MessageType::UnderInvestigation, MessageType::IncidentNoted,
    ];

    public function classify(string $message, ?string $flag = null): ?MessageClassification
    {
        foreach (self::RULES as $rule) {
            [$type, $pattern] = $rule;
            $ruleFlag = $rule[2] ?? null;

            if (preg_match($pattern, $message, $matches) !== 1 && ($ruleFlag === null || $flag !== $ruleFlag)) {
                continue;
            }

            $detail = isset($matches[1]) && $matches[1] !== '' ? $this->detail($type, trim($matches[1])) : null;
            $reason = in_array($type, self::WITH_REASON, true) ? self::reason($message) : null;

            return new MessageClassification($type, $detail, $reason, Offence::fromReason($reason));
        }

        return null;
    }

    private function detail(MessageType $type, string $match): string
    {
        return match ($type) {
            MessageType::PenaltyServed => self::servedPenalty($match),
            MessageType::TimePenalty => "{$match} s",
            MessageType::GridPenalty => "{$match} places",
            MessageType::StopGoPenalty => rtrim(str_replace(' SECOND', ' s', $match)),
            MessageType::SessionStatus => match ($match) {
                'WILL BE EXTENDED' => 'extended',
                'WILL BE TEMPORARILY STOPPED' => 'suspended',
                'START' => 'will start',
                'RESUMPTION' => 'will resume',
                default => strtolower($match),
            },
            MessageType::StartProcedure => match ($match) {
                'SPRINT START' => 'race start',
                'ABORTED START' => 'start aborted',
                'STARTING PROCEDURE SUSPENDED' => 'start suspended',
                'BEHIND' => 'behind the safety car',
                'ORDER FOR RESTART', 'RESTART ORDER', 'RESUMPTION ORDER', 'START ORDER', 'PUSH CARS TO FRONT' => 'grid order',
                default => strtolower($match),
            },
            MessageType::SafetyCarProcedure => $match === 'USE START/FINISH STRAIGHT' ? 'start/finish straight' : strtolower($match),
            MessageType::WetConditions => $match === 'INTERMEDIATE TYRES IS MANDATORY' ? 'intermediates mandatory' : strtolower($match),
            default => strtolower($match),
        };
    }

    /**
     * The penalty a "PENALTY SERVED" message names, as the penalty types word it.
     */
    private static function servedPenalty(string $penalty): string
    {
        return match (true) {
            preg_match('/^(\d+) SECOND TIME$/', $penalty, $matches) === 1 => "{$matches[1]} s time",
            str_contains($penalty, 'STOP') => 'stop-and-go',
            str_contains($penalty, 'DRIVE') => 'drive-through',
            default => strtolower($penalty),
        };
    }

    /**
     * The offence named in a stewards' message: the dash-separated parts that
     * are not about the stage, car or penalty, without the trailing time.
     */
    public static function reason(string $message): ?string
    {
        $parts = array_slice(explode(' - ', $message), 1);
        $parts = array_filter($parts, fn (string $part) => preg_match('/PENALTY (?:FOR|SERVED)|INCIDENT INVOLVING|FLAG FOR CAR|^\d+ SECOND/', $part) !== 1);
        $reason = trim((string) preg_replace('/\s*\(\d{1,2}:\d{2}(?::\d{2})?\)\s*$/', '', implode(' - ', $parts)));

        return $reason === '' ? null : $reason;
    }

    /**
     * A message with its cars, times and numbers replaced by placeholders, so
     * messages that differ only in those group together.
     */
    public static function template(string $message): string
    {
        return (string) preg_replace(
            ['/(?<![^\s,])\d{1,2} \([A-Z]{3}\)/', '/\b\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?\b/', '/\b\d+(?:\.\d+)?\b/'],
            ['<CAR>', '<TIME>', '<N>'],
            $message,
        );
    }
}
