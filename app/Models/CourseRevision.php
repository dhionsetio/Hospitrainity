<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CourseRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Course revisions are immutable; create a new revision instead.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Course revisions are retained as teaching evidence and cannot be deleted.');
        });
    }

    protected $fillable = [
        'course_id',
        'curriculum_package_id',
        'revision_number',
        'title',
        'content_sha256',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function curriculumPackage(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseRevisionModule::class)->orderBy('position');
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}
