<?php

namespace App\Models;

use App\Enums\CourseOfferingStatus;
use App\Services\Time\IanaTimeZone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @property CourseOfferingStatus $status
 * @property string $institution_id
 * @property string|null $timezone
 */
class CourseOffering extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::creating(function (self $offering): void {
            $course = Course::query()->find($offering->course_id);
            $revision = CourseRevision::query()->find($offering->course_revision_id);

            if ($course === null
                || $revision === null
                || $course->institution_id !== $offering->institution_id
                || $revision->course_id !== $offering->course_id) {
                throw new LogicException('A Class must use a Course owned by its Institution and a Revision owned by that Course.');
            }
        });
        static::updating(function (self $offering): void {
            if ($offering->isDirty(['id', 'institution_id', 'course_id', 'course_revision_id', 'key', 'status'])) {
                throw new LogicException('Class identity, ownership, pinned revision, key, and lifecycle state require their approved workflows.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Classes must be archived; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'institution_id',
        'course_id',
        'course_revision_id',
        'key',
        'title',
        'term_label',
        'status',
        'timezone',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['status' => CourseOfferingStatus::class];
    }

    public function setKeyAttribute(mixed $value): void
    {
        $key = Str::slug(trim((string) $value));
        if ($key === '') {
            throw new InvalidArgumentException('Class key must contain at least one letter or number.');
        }

        $this->attributes['key'] = $key;
    }

    protected function timezone(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): ?string => IanaTimeZone::nullable($value),
        );
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class, 'course_revision_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CourseOfferingEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function revisionEvents(): HasMany
    {
        return $this->hasMany(CourseOfferingRevisionEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(ClassAnnouncement::class)->orderByDesc('published_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(InstitutionInvitation::class);
    }

    public function joinCodes(): HasMany
    {
        return $this->hasMany(InstitutionJoinCode::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(InstitutionJoinRequest::class);
    }

    public function acceptsEnrollments(): bool
    {
        return in_array($this->status, [
            CourseOfferingStatus::EnrollmentOpen,
            CourseOfferingStatus::Active,
        ], true);
    }
}
