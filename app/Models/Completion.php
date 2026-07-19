<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Completion extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'completable_id', 'completable_type'];

    /**
     * Build the set of completions a user has recorded, restricted to a specific
     * set of completable ids grouped by fully-qualified model class.
     *
     * The result is a lookup keyed by "{completable_type}|{completable_id}" so a
     * caller can test membership in O(1) with zero further queries. This runs AT
     * MOST ONE database query no matter how many lessons/modules are being scored
     * (and zero queries when there is nothing to look up). That single, bounded
     * query is what lets the dashboard and progress aggregators stay flat as the
     * lesson count grows, replacing the previous 3 count() queries per lesson.
     *
     * Buckets are OR'd per type (type + whereIn ids) rather than flattening all
     * ids together, so an id that exists under two different completable types
     * can never be mis-attributed.
     *
     * @param  array<class-string, array<int>>  $idsByType
     * @return array<string, true>
     */
    public static function completedKeysFor(User $user, array $idsByType): array
    {
        return static::completedKeysForUsers([$user], $idsByType)[$user->getKey()] ?? [];
    }

    /**
     * Fetch completion lookup sets for many learners in one query.
     *
     * @param  iterable<User>  $users
     * @param  array<class-string, array<int>>  $idsByType
     * @return array<int|string, array<string, true>>
     */
    public static function completedKeysForUsers(iterable $users, array $idsByType): array
    {
        $userIds = collect($users)
            ->map(static fn (User $user) => $user->getKey())
            ->filter(static fn ($id): bool => $id !== null)
            ->unique()
            ->values()
            ->all();

        // Drop empty buckets so we never emit an unconstrained OR clause.
        $idsByType = array_filter($idsByType, static fn (array $ids): bool => $ids !== []);

        if ($userIds === [] || $idsByType === []) {
            return [];
        }

        $rows = static::query()
            ->whereIn('user_id', $userIds)
            ->where(function ($query) use ($idsByType) {
                foreach ($idsByType as $type => $ids) {
                    $query->orWhere(function ($inner) use ($type, $ids) {
                        $inner->where('completable_type', $type)
                            ->whereIn('completable_id', $ids);
                    });
                }
            })
            ->get(['user_id', 'completable_type', 'completable_id']);

        $keys = [];
        foreach ($rows as $row) {
            $keys[$row->user_id][$row->completable_type.'|'.$row->completable_id] = true;
        }

        return $keys;
    }

    /**
     * Remove completion rows for curriculum units that are being changed,
     * unpublished, moved, or deleted. Polymorphic targets cannot use ordinary
     * foreign-key cascades, so this cleanup must happen explicitly.
     *
     * @param  array<class-string, array<int>>  $idsByType
     */
    public static function purgeForCompletableIds(array $idsByType): int
    {
        $idsByType = array_filter(array_map(
            static fn (array $ids): array => array_values(array_unique(array_map('intval', $ids))),
            $idsByType,
        ), static fn (array $ids): bool => $ids !== []);

        $deleted = 0;
        if ($idsByType !== []) {
            $deleted = static::query()
                ->where(function ($query) use ($idsByType): void {
                    foreach ($idsByType as $type => $ids) {
                        $query->orWhere(function ($inner) use ($type, $ids): void {
                            $inner->where('completable_type', $type)
                                ->whereIn('completable_id', $ids);
                        });
                    }
                })
                ->delete();
        }

        User::forgetAllProgressCaches();

        return $deleted;
    }
}
