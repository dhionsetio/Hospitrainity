<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class Course extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $course): void {
            if ($course->isDirty(['id', 'institution_id', 'key'])) {
                throw new LogicException('Course identity, institution, and normalized key are immutable.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Courses must be archived; direct deletion is prohibited.');
        });
    }

    protected $fillable = [
        'institution_id',
        'key',
        'title',
        'description',
        'created_by_user_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    public function setKeyAttribute(mixed $value): void
    {
        $key = Str::slug(trim((string) $value));
        if ($key === '') {
            throw new InvalidArgumentException('Course key must contain at least one letter or number.');
        }

        $this->attributes['key'] = $key;
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CourseRevision::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}
