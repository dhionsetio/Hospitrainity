<?php

namespace App\Models;

use App\Enums\CourseEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property CourseEnrollmentStatus $status
 * @property string $course_offering_id
 * @property string $institution_id
 * @property int $institution_membership_id
 */
class CourseEnrollment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $enrollment): void {
            $offering = CourseOffering::query()->find($enrollment->course_offering_id);
            $membership = InstitutionMembership::query()->find($enrollment->institution_membership_id);

            if ($offering === null || $membership === null || $offering->institution_id !== $membership->institution_id) {
                throw new LogicException('A learner may only be enrolled through a membership in the Class Institution.');
            }

            $enrollment->institution_id = $offering->institution_id;
        });
        static::updating(function (self $enrollment): void {
            if ($enrollment->isDirty([
                'course_offering_id',
                'institution_id',
                'institution_membership_id',
                'enrolled_by_user_id',
                'enrolled_at',
            ])) {
                throw new LogicException('Enrollment identity and origin evidence are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Enrollments must be suspended or withdrawn; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'course_offering_id',
        'institution_id',
        'institution_membership_id',
        'status',
        'enrolled_by_user_id',
        'enrolled_at',
        'suspended_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseEnrollmentStatus::class,
            'enrolled_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
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

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CourseEnrollmentEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
