<?php

namespace App\Enums;

enum DataSubjectRequestType: string
{
    case AccessExport = 'access_export';
    case Correction = 'correction';
    case Restriction = 'restriction';
    case Objection = 'objection';
    case Deletion = 'deletion';
    case ConsentWithdrawal = 'consent_withdrawal';
    case Appeal = 'appeal';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
