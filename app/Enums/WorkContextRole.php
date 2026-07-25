<?php

namespace App\Enums;

enum WorkContextRole: string
{
    case Learner = 'learner';
    case Instructor = 'instructor';
    case InstitutionAdmin = 'institution_admin';
    case ContentAuthor = 'content_author';
    case SystemAdmin = 'system_admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    public function landingRoute(): string
    {
        return match ($this) {
            self::Learner => 'dashboard',
            self::Instructor, self::InstitutionAdmin => 'supervisor.dashboard',
            self::ContentAuthor => 'admin.dashboard',
            self::SystemAdmin => 'superadmin.dashboard',
        };
    }
}
