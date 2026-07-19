<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vocabulary extends Model
{
    use HasFactory;

    protected $fillable = ['lesson_id', 'category', 'order'];

    protected static function booted(): void
    {
        static::updated(static function (Vocabulary $vocabulary): void {
            $progressRelevantChanges = array_diff(
                array_keys($vocabulary->getChanges()),
                ['order', 'updated_at'],
            );

            if ($progressRelevantChanges !== []) {
                Completion::purgeForCompletableIds([
                    VocabularyItem::class => $vocabulary->items()->pluck('id')->all(),
                ]);
            }
        });

        static::deleting(static function (Vocabulary $vocabulary): void {
            Completion::purgeForCompletableIds([
                VocabularyItem::class => $vocabulary->items()->pluck('id')->all(),
            ]);
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VocabularyItem::class, 'vocabulary_id')
            ->orderBy('order')
            ->orderBy('id');
    }

    public function scopeCurriculumOrder(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
