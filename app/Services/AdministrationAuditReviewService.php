<?php

namespace App\Services;

use App\Models\CurriculumDraft;
use App\Models\User;
use App\Support\AuditPayloadSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdministrationAuditReviewService
{
    private const PAGE_SIZE = 50;

    /**
     * @param  array{category?:string,event?:string,actor?:string,from?:string,to?:string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $actorIds = $this->actorIds($filters['actor'] ?? null);
        $queries = [];

        if (($filters['category'] ?? null) !== 'curriculum') {
            $queries[] = $this->identityQuery($filters, $actorIds);
        }
        if (($filters['category'] ?? null) !== 'identity') {
            $queries[] = $this->curriculumQuery($filters, $actorIds);
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        /** @var LengthAwarePaginator<int, object> $paginator */
        $paginator = DB::query()
            ->fromSub($union, 'administration_audit_log')
            ->orderByDesc('occurred_at')
            ->orderByDesc('category')
            ->orderByDesc('source_id')
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        $actorAndTargetIds = $paginator->getCollection()
            ->flatMap(static fn (object $entry): array => [$entry->actor_user_id, $entry->target_user_id])
            ->filter()
            ->unique()
            ->values();
        $users = User::query()->whereKey($actorAndTargetIds)->get(['id', 'name'])->keyBy('id');
        $draftIds = $paginator->getCollection()
            ->where('subject_type', 'curriculum_draft')
            ->pluck('subject_id')
            ->filter()
            ->unique()
            ->values();
        $drafts = CurriculumDraft::query()->whereKey($draftIds)
            ->get(['id', 'public_id', 'title', 'content_version'])
            ->keyBy('id');

        $paginator->setCollection($paginator->getCollection()->map(
            fn (object $entry): array => $this->present($entry, $users, $drafts),
        ));

        return $paginator;
    }

    /** @return Collection<int, string> */
    public function eventOptions(?string $category = null): Collection
    {
        $queries = [];
        if ($category !== 'curriculum') {
            $queries[] = DB::table('administration_audits')->select('event');
        }
        if ($category !== 'identity') {
            $queries[] = DB::table('curriculum_draft_events')->selectRaw('event_type AS event');
        }
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->union($query);
        }

        return DB::query()
            ->fromSub($union, 'administration_audit_events')
            ->whereNotNull('event')
            ->orderBy('event')
            ->pluck('event');
    }

    /** @param array<string, string> $filters @param Collection<int, int>|null $actorIds */
    private function identityQuery(array $filters, ?Collection $actorIds): Builder
    {
        $query = DB::table('administration_audits')->select([
            DB::raw("'identity' AS category"),
            'id as source_id',
            'event',
            'actor_user_id',
            'target_user_id',
            DB::raw("'user' AS subject_type"),
            'target_user_id as subject_id',
            'old_role as from_state',
            'new_role as to_state',
            'reason',
            'metadata',
            'ip_address',
            'user_agent',
            'created_at as occurred_at',
        ]);

        return $this->filters($query, $filters, $actorIds, 'event', 'actor_user_id', 'created_at');
    }

    /** @param array<string, string> $filters @param Collection<int, int>|null $actorIds */
    private function curriculumQuery(array $filters, ?Collection $actorIds): Builder
    {
        $query = DB::table('curriculum_draft_events')->select([
            DB::raw("'curriculum' AS category"),
            'id as source_id',
            'event_type as event',
            'actor_id as actor_user_id',
            DB::raw('NULL AS target_user_id'),
            DB::raw("'curriculum_draft' AS subject_type"),
            'curriculum_draft_id as subject_id',
            'from_status as from_state',
            'to_status as to_state',
            'reason',
            'metadata',
            DB::raw('NULL AS ip_address'),
            DB::raw('NULL AS user_agent'),
            'created_at as occurred_at',
        ]);

        return $this->filters($query, $filters, $actorIds, 'event_type', 'actor_id', 'created_at');
    }

    /** @param array<string, string> $filters @param Collection<int, int>|null $actorIds */
    private function filters(
        Builder $query,
        array $filters,
        ?Collection $actorIds,
        string $eventColumn,
        string $actorColumn,
        string $dateColumn,
    ): Builder {
        $query->when($filters['event'] ?? null, fn (Builder $query, string $event): Builder => $query->where($eventColumn, $event));
        if ($actorIds !== null) {
            $actorIds->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn($actorColumn, $actorIds);
        }
        $query->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where($dateColumn, '>=', $from.' 00:00:00'));
        $query->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where($dateColumn, '<=', $to.' 23:59:59'));

        return $query;
    }

    /** @return Collection<int, int>|null */
    private function actorIds(?string $search): ?Collection
    {
        if ($search === null || trim($search) === '') {
            return null;
        }

        $pattern = '%'.trim($search).'%';

        return User::query()
            ->whereLike('name', $pattern)
            ->orWhereLike('email', $pattern)
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, CurriculumDraft>  $drafts
     * @return array<string, mixed>
     */
    private function present(object $entry, Collection $users, Collection $drafts): array
    {
        $metadata = is_string($entry->metadata) ? json_decode($entry->metadata, true) : $entry->metadata;
        $metadata = is_array($metadata) ? AuditPayloadSanitizer::metadata($metadata) : null;
        $subject = $entry->subject_type === 'user'
            ? $users->get($entry->subject_id)
            : $drafts->get($entry->subject_id);

        return [
            'category' => $entry->category,
            'source_id' => (int) $entry->source_id,
            'event' => $entry->event,
            'actor' => $users->get($entry->actor_user_id),
            'target' => $users->get($entry->target_user_id),
            'subject_type' => $entry->subject_type,
            'subject' => $subject,
            'from_state' => $entry->from_state,
            'to_state' => $entry->to_state,
            'reason' => $entry->reason,
            'metadata' => $metadata,
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'occurred_at' => $entry->occurred_at,
        ];
    }
}
