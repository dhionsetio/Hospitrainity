<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    public const TYPES = ['Teks', 'Audio', 'Gambar', 'Video'];

    protected $fillable = ['lesson_id', 'type', 'order'];

    protected static function booted(): void
    {
        static::updated(static function (Material $material): void {
            $progressRelevantChanges = array_diff(
                array_keys($material->getChanges()),
                ['order', 'updated_at'],
            );

            if ($progressRelevantChanges !== []) {
                Completion::purgeForCompletableIds([
                    MaterialItem::class => $material->items()->pluck('id')->all(),
                ]);
            }
        });

        static::deleting(static function (Material $material): void {
            Completion::purgeForCompletableIds([
                MaterialItem::class => $material->items()->pluck('id')->all(),
            ]);
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialItem::class)
            ->orderBy('order')
            ->orderBy('id');
    }

    public function scopeCurriculumOrder(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}
