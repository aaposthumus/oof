<?php

namespace Tests\Unit\RaceControl;

use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
use App\RaceControl\Offence;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessageClassifierTest extends TestCase
{
    /**
     * Real OpenF1 messages with their flag, type and detail.
     *
     * @return array<string, array{0: string, 1: ?string, 2: MessageType, 3: ?string}>
     */
    public static function messages(): array
    {
        return [
            'blue flag' => ['WAVED BLUE FLAG FOR CAR 11 (PER) TIMED AT 15:51:36', 'BLUE', MessageType::BlueFlag, null],
            'lap time deleted' => ['CAR 1 (NOR) TIME 1:34.379 DELETED - TRACK LIMITS AT TURN 19 LAP 15 17:01:02', null, MessageType::TrackLimitsDeletion, null],
            'lap deleted' => ['CAR 1 (NOR) LAP DELETED - TRACK LIMITS AT TURN 20 (NEXT LAP PIT)', null, MessageType::TrackLimitsDeletion, null],
            'reinstated' => ['CAR 27 (HUL) TIME 8:42.988 WILL BE REINSTATED', null, MessageType::TimeReinstated, null],
            'black and white' => ['BLACK AND WHITE FLAG FOR CAR 10 (GAS) - DRIVING UNNECESSARILY SLOWLY', 'BLACK AND WHITE', MessageType::BlackAndWhiteFlag, null],
            'noted' => ['TURN 11 INCIDENT INVOLVING CAR 5 (BOR) NOTED - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:06:39)', null, MessageType::IncidentNoted, null],
            'investigation' => ['FIA STEWARDS: TURN 13 INCIDENT INVOLVING CARS 87 (BEA) AND 10 (GAS) UNDER INVESTIGATION - CAUSING A COLLISION (15:06:57)', null, MessageType::UnderInvestigation, null],
            'after the session' => ['FIA STEWARDS: PIT LANE INCIDENT INVOLVING CARS 27 (HUL) AND 18 (STR) WILL BE INVESTIGATED AFTER THE SESSION (12:41:04)', null, MessageType::InvestigatedAfterSession, null],
            'no further action' => ['FIA STEWARDS: TURN 11 INCIDENT INVOLVING CAR 5 (BOR) NO FURTHER ACTION - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:06:39)', null, MessageType::NoFurtherAction, null],
            'reviewed' => ['FIA STEWARDS: INCIDENT INVOLVING CARS 41 (LIN) AND 55 (SAI) REVIEWED NO FURTHER INVESTIGATION (15:03:49)', null, MessageType::NoFurtherAction, null],
            'time penalty' => ['FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 87 (BEA) - CAUSING A COLLISION (15:06:57)', null, MessageType::TimePenalty, '10 s'],
            'served' => ['FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:04:59)', null, MessageType::PenaltyServed, '5 s time'],
            'vsc' => ['VSC DEPLOYED', null, MessageType::VirtualSafetyCarDeployed, null],
            'vsc ending' => ['VSC ENDING', null, MessageType::VirtualSafetyCarEnding, null],
            'safety car' => ['SAFETY CAR DEPLOYED', null, MessageType::SafetyCarDeployed, null],
            'red flag' => ['RED FLAG', 'RED', MessageType::RedFlag, null],
            'overtake mode' => ['OVERTAKE ENABLED', null, MessageType::OvertakeMode, 'enabled'],
            'clear' => ['TRACK CLEAR', 'CLEAR', MessageType::ClearFlag, null],
            'session' => ['SESSION STARTED', null, MessageType::SessionStatus, 'started'],

            // Stewards
            'served drive-through' => ['FIA STEWARDS: PENALTY SERVED - DRIVE THROUGH PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE', null, MessageType::PenaltyServed, 'drive-through'],
            'served stop/go' => ['FIA STEWARDS: PENALTY SERVED - 10 SECOND STOP/GO PENALTY FOR CAR 87 (BEA) - UNSAFE CONDITION', null, MessageType::PenaltyServed, 'stop-and-go'],
            'served time penalty' => ['FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 5 (BOR) - UNSAFE RELEASE', null, MessageType::PenaltyServed, '5 s time'],
            'stop/go' => ['FIA STEWARDS: 10 SECOND STOP/GO PENALTY FOR CAR 23 (ALB) - UNSAFE CONDITION', null, MessageType::StopGoPenalty, '10 s'],
            'stop-and-go' => ['FIA STEWARDS: STOP-AND-GO PENALTY FOR CAR 43 (COL) - STARTING PROCEDURE INFRINGEMENT', null, MessageType::StopGoPenalty, null],
            'drive-through' => ['FIA STEWARDS: DRIVE THROUGH PENALTY FOR CAR 44 (HAM) - SPEEDING IN THE PIT LANE', null, MessageType::DriveThroughPenalty, null],
            'reprimand' => ['FIA STEWARDS: REPRIMAND (DRIVING) FOR CAR 55 (SAI) - STARTING PROCEDURE INFRINGEMENT', null, MessageType::Reprimand, 'driving'],
            'warning' => ['FIA STEWARDS: WARNING FOR CAR 5 (BOR) - MOVING UNDER BRAKING (16:19:05)', null, MessageType::Warning, null],
            'black flag' => ['FIA STEWARDS: BLACK FLAG FOR CAR 27 (HUL)', null, MessageType::BlackFlag, null],
            'black and white without flag' => ['BLACK AND WHITE FLAG FOR CAR 31 (OCO)', null, MessageType::BlackAndWhiteFlag, null],
            'after the sprint' => ['FIA STEWARDS: TURN 14 INCIDENT INVOLVING CARS 7 (DOO) AND 5 (BOR) WILL BE INVESTIGATED AFTER THE SPRINT - CAUSING A COLLISION', null, MessageType::InvestigatedAfterSession, null],
            'unsafe release noted' => ['PIT LANE INCIDENT INVOLVING CARS 44 (HAM) AND 14 (ALO) NOTED - UNSAFE RELEASE', null, MessageType::IncidentNoted, null],
            'unsafe release cleared' => ['FIA STEWARDS: PIT LANE INCIDENT INVOLVING CARS 44 (HAM) AND 14 (ALO) NO FURTHER ACTION - UNSAFE RELEASE', null, MessageType::NoFurtherAction, null],
            'false start investigated' => ['FIA STEWARDS: INCIDENT INVOLVING CAR 27 (HUL) UNDER INVESTIGATION - FALSE START - MOVING BEFORE SIGNAL', null, MessageType::UnderInvestigation, null],
            'incidents noted' => ['FIA STEWARDS: SQ1 INCIDENTS INVOLVING CARS 63 (RUS), 4 (NOR), 81 (PIA), 31 (OCO) NOTED - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS - MAXIMUM DELTA TIME', null, MessageType::IncidentNoted, null],
            'incident without stage' => ['PIT LANE INCIDENT INVOLVING CAR 27 (HUL) - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS', null, MessageType::IncidentNoted, null],
            'lap and turn noted' => ['LAP 1 TURN 1 NOTED', null, MessageType::IncidentNoted, null],
            'deletion by acronym' => ['CAR LEC TIME 1:36.518 DELETED - TRACK LIMITS AT TURN 14 (NEXT LAP) (Q1)', null, MessageType::TrackLimitsDeletion, null],
            'double yellow deletion' => ['CAR 41 (LIN) TIME 1:38.588 DELETED - DOUBLE YELLOW AT TURN 16', null, MessageType::TimeDeletedOther, null],

            // Red flag, safety car and start
            'red flag suspended' => ['RED FLAG - RACE SUSPENDED', null, MessageType::RedFlag, null],
            'virtual safety car' => ['VIRTUAL SAFETY CAR DEPLOYED', null, MessageType::VirtualSafetyCarDeployed, null],
            'safety car enters pits' => ['SAFETY CAR WILL ENTER PITS: ROLLING START PROCEDURE', null, MessageType::SafetyCarInThisLap, null],
            'safety car lights' => ['SAFETY CAR LIGHTS ON', null, MessageType::SafetyCarProcedure, 'lights on'],
            'through the pit lane' => ['ALL CARS TO FOLLOW THE SAFETY CAR THROUGH THE PIT LANE', null, MessageType::SafetyCarProcedure, 'through the pit lane'],
            'start/finish straight' => ['SAFETY CAR WILL USE START/FINISH STRAIGHT', null, MessageType::SafetyCarProcedure, 'start/finish straight'],
            'lapped car' => ['LAPPED CAR MAY NOW OVERTAKE THE SAFETY CAR: 24', null, MessageType::LappedCars, null],
            'start behind the safety car' => ['FORMATION LAP WILL BE STARTED BEHIND THE SAFETY CAR ON WET-WEATHER TYRES', null, MessageType::StartProcedure, 'behind the safety car'],
            'formation laps behind the safety car' => ['FORMATION LAP(S) BEHIND SAFETY CAR', null, MessageType::StartProcedure, 'behind the safety car'],
            'standing start' => ['STANDING START', null, MessageType::StartProcedure, 'standing start'],
            'aborted start' => ['ABORTED START', null, MessageType::StartProcedure, 'start aborted'],
            'restart order' => ['ORDER FOR RESTART: 1, 16, 44, 55, 3, 81, 63, 22, 27, 4, 10, 23, 24, 31, 2, 77, 18, 14', null, MessageType::StartProcedure, 'grid order'],

            // Session
            'aborted' => ['SESSION ABORTED', null, MessageType::SessionStatus, 'aborted'],
            'segment start' => ['Q3 WILL START AT 18:56', null, MessageType::SessionStatus, 'will start'],
            'race resumes' => ['RACE WILL RESUME AT 15:33 - STANDING START PROCEDURE', null, MessageType::SessionStatus, 'will resume'],
            'estimated resumption' => ['ESTIMATED TIME OF Q2 RESUMPTION - 15:39', null, MessageType::SessionStatus, 'will resume'],
            'delayed' => ['START OF Q2 WILL BE DELAYED', null, MessageType::SessionStatus, 'delayed'],
            'not resumed' => ['SQ1 WILL NOT BE RESUMED', null, MessageType::SessionStatus, 'will not be resumed'],
            'extended' => ['FREE PRACTICE 1 WILL BE EXTENDED BY 4 MINUTES', null, MessageType::SessionStatus, 'extended'],
            'temporarily stopped' => ['SESSION WILL BE TEMPORARILY STOPPED', null, MessageType::SessionStatus, 'suspended'],

            // DRS and pit lane
            'drs' => ['DRS ENABLED', null, MessageType::Drs, 'enabled'],
            'drs in a zone' => ['DRS DISABLED IN ZONE 2', null, MessageType::Drs, 'disabled in zone'],
            'pit lane entry' => ['PIT LANE ENTRY CLOSED', null, MessageType::PitEntry, 'closed'],
            'pit lane clear' => ['PIT LANE CLEAR', null, MessageType::ClearFlag, null],

            // Conditions and cars
            'wet track' => ['WET TRACK', null, MessageType::WetConditions, 'wet track'],
            'temperature' => ['AIR TEMPERATURE 3 HOURS BEFORE THE RACE = 20.8 DEGREES', null, MessageType::Temperature, null],
            'spun' => ['CAR 55 (SAI) SPUN AND CONTINUED AT TURN 9', null, MessageType::OffTrack, 'spun'],
            'off track corrected' => ['CORRECTION: CAR 1 (VER) OFF TRACK AND CONTINUED AT TURN 11', null, MessageType::OffTrack, 'off track'],
            'stopped on the straight' => ['CAR 1 (VER) STOPPED ON START/FINISH STRAIGHT', null, MessageType::CarStopped, null],

            // Notices that concern no driver
            'head padding' => ['PINK HEAD PADDING MATERIAL MUST BE USED', null, MessageType::Notice, null],
            'covers on' => ['COVERS ON TIME 2 HOURS AFTER CHEQUERED FLAG = 19:03', null, MessageType::Notice, null],
            'parc ferme' => ['F1 - POST-SPRINT PARC FERME - WORK MAY COMMENCE AT 13:32', null, MessageType::Notice, null],
        ];
    }

    #[DataProvider('messages')]
    public function test_classifies_real_messages(string $message, ?string $flag, MessageType $type, ?string $detail): void
    {
        $classification = (new MessageClassifier)->classify($message, $flag);

        $this->assertSame($type, $classification?->type);
        $this->assertSame($detail, $classification->detail);
    }

    public function test_extracts_the_offence(): void
    {
        $this->assertSame('CAUSING A COLLISION', (new MessageClassifier)->classify('FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 87 (BEA) - CAUSING A COLLISION (15:06:57)')->reason);
        $this->assertSame('LEAVING THE TRACK AND GAINING AN ADVANTAGE', (new MessageClassifier)->classify('FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:04:59)')->reason);
        $this->assertNull((new MessageClassifier)->classify('INCIDENT INVOLVING CARS 41 (LIN) AND 55 (SAI) NOTED (15:03:49)')->reason);
    }

    /**
     * Real stewards' messages with the offence they name.
     *
     * @return array<string, array{0: string, 1: Offence}>
     */
    public static function offences(): array
    {
        return [
            'unsafe release' => ['FIA STEWARDS: PIT LANE INCIDENT INVOLVING CARS 44 (HAM) AND 14 (ALO) UNDER INVESTIGATION - UNSAFE RELEASE', Offence::UnsafeRelease],
            'released unsafely' => ['FIA STEWARDS: 10 SECOND STOP/GO PENALTY FOR CAR 87 (BEA) - RELEASING IN UNSAFE CONDITION', Offence::UnsafeRelease],
            'false start' => ['INCIDENT INVOLVING CAR 5 (BOR) NOTED - FALSE START - MOVING BEFORE SIGNAL', Offence::FalseStart],
            'jump start' => ['FIA STEWARDS: CORRECTION: INCIDENT INVOLVING CAR 4 (NOR) NO FURTHER ACTION - JUMP START', Offence::FalseStart],
            'leaving the track' => ['FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:04:59)', Offence::LeavingTheTrack],
            'leaving the track repeatedly' => ['FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 4 (NOR) - LEAVING THE TRACK MULTIPLE TIMES AND GAINING AN ADVANTAGE', Offence::LeavingTheTrack],
            'track limits' => ['FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 18 (STR) - TRACK LIMITS (5TH OFFENCE)', Offence::TrackLimits],
            'track limits as instruction' => ['INCIDENT INVOLVING CAR 1 (VER) NOTED - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS – TRACK LIMITS', Offence::TrackLimits],
            'collision' => ['FIA STEWARDS: 10 SECOND TIME PENALTY FOR CAR 87 (BEA) - CAUSING A COLLISION (15:06:57)', Offence::CausingACollision],
            'start position' => ['FIA STEWARDS: WARNING FOR CAR 55 (SAI) - STARTING PROCEDURE INFRINGEMENT - OUT OF POSITION AT SAFETY CAR LINE (12:02:45)', Offence::StartingProcedure],
            'pit lane under safety car' => ['INCIDENT INVOLVING CAR 2 (SAR) NOTED - ENTERING PIT LANE UNDER SAFETY CAR CONDITIONS', Offence::SafetyCarInfringement],
            'maximum delta' => ['FIA STEWARDS: Q1 INCIDENT INVOLVING CARS 6 (HAD), 77 (BOT) NO FURTHER ACTION - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS - MAXIMUM DELTA TIME', Offence::MaximumTime],
            'escape road' => ['FIA STEWARDS: REPRIMAND (DRIVING) FOR CAR 55 (SAI) - FAILING TO FOLLOW RACE DIRECTORS INSTRUCTIONS – ESCAPE ROAD INSTRUCTIONS', Offence::RaceDirectorsInstructions],
            'black and white' => ['BLACK AND WHITE FLAG FOR CAR 10 (GAS) - DRIVING UNNECESSARILY SLOWLY', Offence::DrivingSlowly],
            'moving under braking' => ['BLACK AND WHITE FLAG FOR CAR 20 (MAG) - MOVING UNDER BRAKING', Offence::MovingUnderBraking],
            'change of direction' => ['FIA STEWARDS: 5 SECOND TIME PENALTY FOR CAR 1 (VER) - MORE THAN ONE CHANGE OF DIRECTION', Offence::ErraticDriving],
        ];
    }

    #[DataProvider('offences')]
    public function test_groups_the_offence(string $message, Offence $offence): void
    {
        $this->assertSame($offence, (new MessageClassifier)->classify($message)->offence);
    }

    public function test_has_no_offence_without_a_reason(): void
    {
        $this->assertNull((new MessageClassifier)->classify('CAR 1 (NOR) TIME 1:34.379 DELETED - TRACK LIMITS AT TURN 19 LAP 15 17:01:02')->offence);
        $this->assertNull((new MessageClassifier)->classify('FIA STEWARDS: INCIDENT INVOLVING CAR 6 (HAD) NOTED - RACE CONTROL TEST')->offence);
    }

    public function test_leaves_unknown_wordings_unclassified(): void
    {
        $this->assertNull((new MessageClassifier)->classify('LAP TIME OF CAR 87 (BEA) - UNDER REVIEW'));
    }

    public function test_templates_replace_cars_times_and_numbers(): void
    {
        $this->assertSame(
            'CAR <CAR> TIME <TIME> DELETED - TRACK LIMITS AT TURN <N> LAP <N> <TIME>',
            MessageClassifier::template('CAR 18 (STR) TIME 1:39.171 DELETED - TRACK LIMITS AT TURN 12 LAP 5 12:38:48'),
        );
        $this->assertSame(
            'CAR <CAR> LAP DELETED - TRACK LIMITS AT TURN <N> LAP <N> <TIME> (PIT)',
            MessageClassifier::template('CAR 14 (ALO) LAP DELETED - TRACK LIMITS AT TURN 4 LAP 34 18:58:59 (PIT)'),
        );
    }
}
