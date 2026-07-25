<?php

namespace App\Models;

use App\Enums\CourseEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CourseEnrollmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Class enrollment events are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Class enrollment events are append-only evidence.');
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => CourseEnrollmentStatus::class,
            'to_status' => CourseEnrollmentStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id');
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function transferTarget(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'transfer_to_course_offering_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
