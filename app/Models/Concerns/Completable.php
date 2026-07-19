<?php

namespace App\Models\Concerns;

use App\Models\Completion;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Completable
{
    public static function bootCompletable(): void
    {
        static::created(static function (): void {
            User::forgetAllProgressCaches();
        });

        static::updated(static function ($model): void {
            $progressRelevantChanges = array_diff(
                array_keys($model->getChanges()),
                ['order', 'updated_at'],
            );

            if ($progressRelevantChanges !== []) {
                Completion::purgeForCompletableIds([
                    $model::class => [$model->getKey()],
                ]);
            }
        });

        static::deleting(static function ($model): void {
            Completion::purgeForCompletableIds([
                $model::class => [$model->getKey()],
            ]);
        });
    }

    public function completions(): MorphMany
    {
        return $this->morphMany(Completion::class, 'completable');
    }
}
