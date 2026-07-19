<?php

namespace App\Enums;

enum UserRole: string
{
    case Learner = 'user';
    case Supervisor = 'supervisor';
    case Admin = 'admin';
    case Superadmin = 'superadmin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /** @return list<self> */
    public static function assignableWithoutSuperadmin(): array
    {
        return [self::Learner, self::Supervisor, self::Admin];
    }

    public function landingRoute(): string
    {
        return match ($this) {
            self::Learner => 'dashboard',
            self::Supervisor => 'supervisor.dashboard',
            self::Admin => 'admin.dashboard',
            self::Superadmin => 'superadmin.dashboard',
        };
    }

    public function isElevated(): bool
    {
        return $this !== self::Learner;
    }
}
