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
        [MessageType::PenaltyServed, '/PENALTY SERVED/'],
        [MessageType::TimePenalty, '/(\d+) SECOND TIME PENALTY/'],
        [MessageType::StopGoPenalty, '/(\d+ SECOND )?STOP\/GO PENALTY|STOP AND GO PENALTY/'],
        [MessageType::DriveThroughPenalty, '/DRIVE THROUGH PENALTY/'],
        [MessageType::GridPenalty, '/(\d+) PLACE GRID PENALTY/'],
        [MessageType::PitLaneStart, '/START FROM THE PIT LANE|PIT LANE START/'],
        [MessageType::Reprimand, '/REPRIMAND/'],
        [MessageType::Disqualification, '/DISQUALIFIED/'],
        [MessageType::BlackAndOrangeFlag, '/BLACK AND ORANGE/', 'BLACK AND ORANGE'],
        [MessageType::BlackAndWhiteFlag, '/BLACK AND WHITE FLAG/', 'BLACK AND WHITE'],
        [MessageType::BlackFlag, '/\bBLACK FLAG\b/', 'BLACK'],
        [MessageType::UnsafeRelease, '/UNSAFE RELEASE/'],
        [MessageType::FalseStart, '/FALSE START|JUMP START|JUMPED START/'],
        [MessageType::NoFurtherAction, '/NO FURTHER ACTION|NO FURTHER INVESTIGATION|NO INVESTIGATION NECESSARY/'],
        [MessageType::InvestigatedAfterSession, '/WILL BE INVESTIGATED AFTER THE (?:SESSION|RACE)/'],
        [MessageType::UnderInvestigation, '/UNDER INVESTIGATION/'],
        [MessageType::IncidentNoted, '/INCIDENT .*NOTED/'],
        [MessageType::TrackLimitsDeletion, '/DELETED - TRACK LIMITS/'],
        [MessageType::TimeDeletedOther, '/(?:TIME|LAP)\b.* DELETED/'],
        [MessageType::TimeReinstated, '/REINSTATED/'],
        [MessageType::RedFlag, '/\bRED FLAG\b/', 'RED'],
        [MessageType::VirtualSafetyCarDeployed, '/(?:VIRTUAL SAFETY CAR|VSC) DEPLOYED/'],
        [MessageType::VirtualSafetyCarEnding, '/(?:VIRTUAL SAFETY CAR|VSC) ENDING/'],
        [MessageType::SafetyCarDeployed, '/SAFETY CAR DEPLOYED/'],
        [MessageType::SafetyCarInThisLap, '/SAFETY CAR IN THIS LAP/'],
        [MessageType::SafetyCarEnding, '/SAFETY CAR ENDING/'],
        [MessageType::BlueFlag, '/BLUE FLAG/', 'BLUE'],
        [MessageType::DoubleYellowFlag, '/DOUBLE YELLOW/', 'DOUBLE YELLOW'],
        [MessageType::YellowFlag, '/^YELLOW/', 'YELLOW'],
        [MessageType::ClearFlag, '/^(?:TRACK )?CLEAR/', 'CLEAR'],
        [MessageType::GreenFlag, '/^GREEN/', 'GREEN'],
        [MessageType::ChequeredFlag, '/CHEQUERED FLAG/', 'CHEQUERED'],
        [MessageType::LappedCars, '/LAPPED CARS/'],
        [MessageType::Drs, '/DRS (ENABLED|DISABLED)/'],
        [MessageType::OvertakeMode, '/OVERTAKE (ENABLED|DISABLED)/'],
        [MessageType::PitExit, '/PIT EXIT (OPEN|CLOSED)/'],
        [MessageType::PitEntry, '/PIT ENTRY (OPEN|CLOSED)/'],
        [MessageType::PitLaneAccess, '/PASS HOLDERS|PIT LANE (?:IS )?(OPEN|CLOSED)/'],
        [MessageType::SessionStatus, '/^SESSION (STARTED|FINISHED|RESUMED|SUSPENDED|WILL RESUME)/'],
        [MessageType::StartProcedure, '/(RACE START|FORMATION LAP|START ABORTED|RESUMPTION)/'],
        [MessageType::FirstCarToTakeTheFlag, '/FIRST CAR TO TAKE THE FLAG/'],
        [MessageType::RiskOfRain, '/RISK OF RAIN/'],
        [MessageType::GripConditions, '/(LOW|NORMAL) GRIP/'],
        [MessageType::SlipperySurface, '/SLIPPERY/'],
        [MessageType::MarshalsOnTrack, '/MARSHALS ON TRACK/'],
        [MessageType::CarStopped, '/STOPPED|RECOVERY VEHICLE/'],
        [MessageType::MedicalCar, '/MEDICAL CAR/'],
    ];

    /**
     * Types whose messages name an offence after a dash, e.g. "... NOTED - IMPEDING".
     */
    private const WITH_REASON = [
        MessageType::PenaltyServed, MessageType::TimePenalty, MessageType::StopGoPenalty, MessageType::DriveThroughPenalty,
        MessageType::GridPenalty, MessageType::PitLaneStart, MessageType::Reprimand, MessageType::Disqualification,
        MessageType::BlackFlag, MessageType::BlackAndOrangeFlag, MessageType::BlackAndWhiteFlag, MessageType::UnsafeRelease,
        MessageType::FalseStart, MessageType::NoFurtherAction, MessageType::InvestigatedAfterSession,
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

            return new MessageClassification($type, $detail, $reason);
        }

        return null;
    }

    private function detail(MessageType $type, string $match): string
    {
        return match ($type) {
            MessageType::TimePenalty => "{$match} s",
            MessageType::GridPenalty => "{$match} places",
            MessageType::StopGoPenalty => rtrim(str_replace(' SECOND', ' s', $match)),
            default => strtolower($match),
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
            ['/\b\d{1,2} \([A-Z]{3}\)/', '/\b\d{1,2}:\d{2}(?::\d{2})?(?:\.\d+)?\b/', '/\b\d+(?:\.\d+)?\b/'],
            ['<CAR>', '<TIME>', '<N>'],
            $message,
        );
    }
}
