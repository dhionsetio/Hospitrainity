<?php

namespace App\Models;

use App\Enums\TeachingAssignmentRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property TeachingAssignmentRole $role */
class TeachingAssignment extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            // This is an active-row uniqueness projection, not historical role
            // evidence. Revoking a primary frees the slot while `role` remains.
            $assignment->primary_slot = $assignment->role === TeachingAssignmentRole::Primary
                && $assignment->revoked_at === null
                    ? 1
                    : null;
            $assignment->active_membership_slot = $assignment->revoked_at === null
                ? $assignment->institution_membership_id
                : null;
        });
        static::creating(function (self $assignment): void {
            $offering = CourseOffering::query()->find($assignment->course_offering_id);
            $membership = InstitutionMembership::query()->find($assignment->institution_membership_id);

            if ($offering === null || $membership === null || $offering->institution_id !== $membership->institution_id) {
                throw new LogicException('An instructor may only be assigned through a membership in the Class Institution.');
            }

            $assignment->institution_id = $offering->institution_id;
        });
        static::updating(function (self $assignment): void {
            if ($assignment->isDirty([
                'course_offering_id',
                'institution_id',
                'institution_membership_id',
                'role',
                'assigned_by_user_id',
                'assigned_at',
            ])) {
                throw new LogicException('Teaching assignment identity and origin evidence are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Teaching assignments must be revoked; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'course_offering_id',
        'institution_id',
        'institution_membership_id',
        'active_membership_slot',
        'role',
        'primary_slot',
        'assigned_by_user_id',
        'assigned_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => TeachingAssignmentRole::class,
            'primary_slot' => 'integer',
            'active_membership_slot' => 'integer',
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(InstitutionMembership::class, 'institution_membership_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
