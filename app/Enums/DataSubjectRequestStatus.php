<?php

namespace App\Enums;

enum DataSubjectRequestStatus: string
{
    case Submitted = 'submitted';
    case IdentityPending = 'identity_pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Denied = 'denied';
    case Held = 'held';
    case Executing = 'executing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Appealed = 'appealed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Denied, self::Completed, self::Cancelled], true);
    }
}
