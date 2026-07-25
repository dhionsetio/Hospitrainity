<?php

namespace App\Models;

use App\Enums\InstitutionMembershipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property InstitutionMembershipStatus $status
 * @property string $institution_id
 */
class InstitutionMembership extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $membership): void {
            if ($membership->isDirty(['institution_id', 'user_id', 'provenance'])) {
                throw new LogicException('Membership identity and provenance are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Memberships must be revoked or archived; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'institution_id',
        'user_id',
        'status',
        'is_default',
        'provenance',
        'joined_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InstitutionMembershipStatus::class,
            'is_default' => 'boolean',
            'joined_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(InstitutionRoleAssignment::class);
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
