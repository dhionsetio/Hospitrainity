<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CourseRevisionModule extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Course revision module selections are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Course revision module selections are retained as teaching evidence.');
        });
    }

    protected $fillable = [
        'course_revision_id',
        'curriculum_package_id',
        'curriculum_entity_id',
        'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(CourseRevision::class, 'course_revision_id');
    }

    public function curriculumEntity(): BelongsTo
    {
        return $this->belongsTo(CurriculumEntity::class);
    }

    public function curriculumPackage(): BelongsTo
    {
        return $this->belongsTo(CurriculumPackage::class);
    }
}
