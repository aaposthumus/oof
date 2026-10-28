<?php

namespace Tests\Unit\RaceControl;

use App\RaceControl\MessageClassifier;
use App\RaceControl\MessageType;
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
            'served' => ['FIA STEWARDS: PENALTY SERVED - 5 SECOND TIME PENALTY FOR CAR 63 (RUS) - LEAVING THE TRACK AND GAINING AN ADVANTAGE (15:04:59)', null, MessageType::PenaltyServed, null],
            'vsc' => ['VSC DEPLOYED', null, MessageType::VirtualSafetyCarDeployed, null],
            'vsc ending' => ['VSC ENDING', null, MessageType::VirtualSafetyCarEnding, null],
            'safety car' => ['SAFETY CAR DEPLOYED', null, MessageType::SafetyCarDeployed, null],
            'red flag' => ['RED FLAG', 'RED', MessageType::RedFlag, null],
            'overtake mode' => ['OVERTAKE ENABLED', null, MessageType::OvertakeMode, 'enabled'],
            'clear' => ['TRACK CLEAR', 'CLEAR', MessageType::ClearFlag, null],
            'session' => ['SESSION STARTED', null, MessageType::SessionStatus, 'started'],
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

    public function test_leaves_unknown_wordings_unclassified(): void
    {
        $this->assertNull((new MessageClassifier)->classify('PINK HEAD PADDING MATERIAL MUST BE USED'));
    }

    public function test_templates_replace_cars_times_and_numbers(): void
    {
        $this->assertSame(
            'CAR <CAR> TIME <TIME> DELETED - TRACK LIMITS AT TURN <N> LAP <N> <TIME>',
            MessageClassifier::template('CAR 18 (STR) TIME 1:39.171 DELETED - TRACK LIMITS AT TURN 12 LAP 5 12:38:48'),
        );
    }
}
