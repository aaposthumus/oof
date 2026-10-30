<?php

namespace App\Enums;

/**
 * Why an award waits for a person to confirm or suppress it.
 */
enum AwardReviewReason: string
{
    case LowConfidence = 'low_confidence';
    case NoClearCause = 'no_clear_cause';
    case OpenInvestigation = 'open_investigation';
    case SourceDisagreement = 'source_disagreement';
    case SensitiveWeekend = 'sensitive_weekend';

    public function label(): string
    {
        return match ($this) {
            self::LowConfidence => 'Low confidence',
            self::NoClearCause => 'No clear cause',
            self::OpenInvestigation => 'Open investigation',
            self::SourceDisagreement => 'Sources disagree',
            self::SensitiveWeekend => 'Sensitive weekend',
        };
    }
}
