<?php

namespace App\Enums;

enum CourseOfferingStatus: string
{
    case Draft = 'draft';
    case EnrollmentOpen = 'enrollment_open';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
