<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ClassAnnouncement extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $announcement): void {
            if ($announcement->isDirty([
                'id',
                'course_offering_id',
                'institution_id',
                'curriculum_package_id',
                'curriculum_entity_id',
                'created_by_user_id',
                'published_at',
            ])) {
                throw new LogicException('Class announcement identity and publication evidence are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Class announcements must be archived; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'course_offering_id',
        'institution_id',
        'curriculum_package_id',
        'curriculum_entity_id',
        'title',
        'body',
        'revision',
        'created_by_user_id',
        'updated_by_user_id',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'published_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CurriculumEntity::class, 'curriculum_entity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
