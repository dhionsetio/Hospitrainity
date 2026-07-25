<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CourseOfferingRevisionEvent extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Class content release events are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Class content release events are append-only evidence.');
        });
    }

    protected $fillable = [
        'course_offering_id',
        'institution_id',
        'from_course_revision_id',
        'to_course_revision_id',
        'actor_user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function fromRevision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class, 'from_course_revision_id');
    }

    public function toRevision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class, 'to_course_revision_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
