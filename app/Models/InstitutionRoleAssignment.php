<?php

namespace App\Models;

use App\Enums\InstitutionRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InstitutionRoleAssignment extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $assignment): void {
            if ($assignment->isDirty(['institution_membership_id', 'role', 'assigned_by_user_id', 'assigned_at'])) {
                throw new LogicException('Institution role assignment evidence is immutable; revoke or restore the assignment.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Institution role assignments must be revoked; direct deletion is prohibited.');
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => InstitutionRole::class,
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(InstitutionMembership::class, 'institution_membership_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
