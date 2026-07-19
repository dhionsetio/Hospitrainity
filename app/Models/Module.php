<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'level',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(static function (Module $module): void {
            if ($module->is_published) {
                User::forgetAllProgressCaches();
            }
        });

        static::updated(static function (Module $module): void {
            if ($module->wasChanged('is_published')) {
                Completion::purgeForCompletableIds($module->completableIdsByType());
            }
        });

        static::deleting(static function (Module $module): void {
            Completion::purgeForCompletableIds($module->completableIdsByType());
        });
    }

    /**
     * Mendefinisikan relasi "satu-ke-banyak": Satu Module memiliki banyak Lesson.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'module_id', 'id')
            ->orderBy('order')
            ->orderBy('id');
    }

    public function scopeCurriculumOrder(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * Average completion percentage (0-100) of this module for a user.
     *
     * Eager-loads every lesson's progress relations, then delegates to
     * Lesson::averageProgressForUser which fetches the user's completions in a
     * SINGLE query. Query count is bounded and independent of the lesson count
     * (previously each lesson re-queried its relations plus 3 count() queries).
     */
    public function getProgressForUser(User $user): int
    {
        $lessons = $this->lessons()->with(Lesson::PROGRESS_RELATIONS)->get();

        return Lesson::averageProgressForUser($lessons, $user);
    }

    /** @return array<class-string, array<int>> */
    private function completableIdsByType(): array
    {
        $lessons = $this->lessons()->with(Lesson::PROGRESS_RELATIONS)->get();

        return Lesson::mergeIdsByType($lessons);
    }
}
