<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['module_id', 'title', 'slug', 'order'];

    /**
     * Relations that must be loaded to score a lesson without extra queries.
     * Eager-load these (e.g. `->with(Lesson::PROGRESS_RELATIONS)`) before calling
     * the progress helpers to keep the query count flat.
     */
    public const PROGRESS_RELATIONS = ['vocabularies.items', 'materials.items', 'exercises'];

    protected static function booted(): void
    {
        static::created(static function (Lesson $lesson): void {
            if ($lesson->module()->where('is_published', true)->exists()) {
                User::forgetAllProgressCaches();
            }
        });

        static::updated(static function (Lesson $lesson): void {
            if ($lesson->wasChanged('module_id')) {
                Completion::purgeForCompletableIds($lesson->completableIdsByType());
            }
        });

        static::deleting(static function (Lesson $lesson): void {
            Completion::purgeForCompletableIds($lesson->completableIdsByType());
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function vocabularies(): HasMany
    {
        return $this->hasMany(Vocabulary::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function scopeCurriculumOrder(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * This lesson's completable ids, grouped by fully-qualified model class,
     * read from ALREADY-LOADED relations.
     *
     * A lesson is made of three kinds of completable units, each tracked via the
     * polymorphic `completions` table:
     *   - Vocabulary items (VocabularyItem)
     *   - Material items    (MaterialItem)
     *   - Exercises         (Exercise, which is itself the completable unit)
     *
     * `loadMissing` makes the method safe to call on an un-hydrated lesson (it
     * lazy-loads each relation once), but callers that pre-eager-load
     * PROGRESS_RELATIONS pay nothing here — no query is issued.
     *
     * @return array<class-string, array<int>>
     */
    public function completableIdsByType(): array
    {
        $this->loadMissing(self::PROGRESS_RELATIONS);

        $vocabItemIds = $this->vocabularies
            ->flatMap(fn ($category) => $category->items->pluck('id'))
            ->all();

        $materialItemIds = $this->materials
            ->flatMap(fn ($material) => $material->items->pluck('id'))
            ->all();

        $exerciseIds = $this->exercises->pluck('id')->all();

        return [
            VocabularyItem::class => $vocabItemIds,
            MaterialItem::class => $materialItemIds,
            Exercise::class => $exerciseIds,
        ];
    }

    /**
     * Percentage (0-100) of this lesson completed, computed purely in PHP from
     * loaded relations and a pre-fetched set of completed "{type}|{id}" keys.
     * Issues NO database queries.
     *
     * All three completable kinds are included in both the numerator and the
     * denominator so the bar reflects real, total lesson completion.
     *
     * @param  array<string, true>  $completedKeys
     */
    public function progressFromKeys(array $completedKeys): int
    {
        $totalItems = 0;
        $completedItems = 0;

        foreach ($this->completableIdsByType() as $type => $ids) {
            $totalItems += count($ids);
            foreach ($ids as $id) {
                if (isset($completedKeys[$type.'|'.$id])) {
                    $completedItems++;
                }
            }
        }

        if ($totalItems === 0) {
            return 0; // Avoid division by zero
        }

        return (int) round(($completedItems / $totalItems) * 100);
    }

    /**
     * Percentage (0-100) of this lesson a user has completed.
     *
     * Backward-compatible single-lesson entry point. Bounded query count: this
     * lesson's relations are eager-loaded if needed, then the user's relevant
     * completions are fetched in ONE query (previously this method issued a fresh
     * vocab/material/exercise load plus up to 3 count() queries every call).
     */
    public function getProgressFor(User $user): int
    {
        $completedKeys = Completion::completedKeysFor($user, $this->completableIdsByType());

        return $this->progressFromKeys($completedKeys);
    }

    /**
     * Average progress (0-100) across a collection of lessons for one user,
     * using a SINGLE completions query for the whole set. Empty set => 0.
     *
     * @param  iterable<Lesson>  $lessons
     */
    public static function averageProgressForUser(iterable $lessons, User $user): int
    {
        $lessons = collect($lessons);

        if ($lessons->isEmpty()) {
            return 0;
        }

        $completedKeys = Completion::completedKeysFor($user, static::mergeIdsByType($lessons));

        $totalProgress = 0;
        foreach ($lessons as $lesson) {
            $totalProgress += $lesson->progressFromKeys($completedKeys);
        }

        return (int) round($totalProgress / $lessons->count());
    }

    /**
     * Merge the completable ids of many lessons into one type-grouped map so a
     * single completions query can cover every lesson at once.
     *
     * @param  iterable<Lesson>  $lessons
     * @return array<class-string, array<int>>
     */
    public static function mergeIdsByType(iterable $lessons): array
    {
        $merged = [
            VocabularyItem::class => [],
            MaterialItem::class => [],
            Exercise::class => [],
        ];

        foreach ($lessons as $lesson) {
            foreach ($lesson->completableIdsByType() as $type => $ids) {
                if ($ids !== []) {
                    $merged[$type] = array_merge($merged[$type], $ids);
                }
            }
        }

        return $merged;
    }
}
