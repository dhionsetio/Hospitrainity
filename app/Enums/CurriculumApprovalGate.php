<?php

namespace App\Enums;

enum CurriculumApprovalGate: string
{
    case Content = 'content';
    case EspHospitality = 'esp_hospitality';
    case Cefr = 'cefr';
    case Accessibility = 'accessibility';
    case RightsLinks = 'rights_links';
    case Retention = 'retention';
    case FinalOwner = 'final_owner';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $gate): string => $gate->value, self::cases());
    }
}
