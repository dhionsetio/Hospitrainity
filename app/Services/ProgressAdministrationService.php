<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ProgressAdministrationService
{
    private const LEARNERS_PER_PAGE = 20;

    private const STATE_COLUMNS = [
        'viewed' => 'viewed_at',
        'started' => 'started_at',
        'attempted' => 'attempted_at',
        'self_checked' => 'self_checked_at',
        'completed' => 'completed_at',
    ];

    public function __construct(private readonly CurriculumProgressService $progress) {}

    /**
     * Return a fixed-size identity-level page. Every related metadata query is
     * batched for the displayed user IDs; no query is issued from a row loop.
     *
     * @param  array<string, mixed>  $filters
     */
    public function identityLearners(array $filters): LengthAwarePaginator
    {
        $sectionCodes = $this->sectionCodesForModule($filters);
        $hasProgressFilter = collect(['version', 'module', 'status', 'recent'])
            ->contains(static fn (string $key): bool => filled($filters[$key] ?? null));

        $query = User::query()
            ->select(['id', 'name', 'instansi', 'email', 'email_verified_at', 'role', 'created_at'])
            ->where('role', UserRole::Learner->value)
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $pattern = '%'.trim($search).'%';
                    $query->whereLike('name', $pattern)
                        ->orWhereLike('email', $pattern)
                        ->orWhereLike('instansi', $pattern);
                });
            })
            ->when($filters['institution'] ?? null, fn (Builder $query, string $institution): Builder => $query->where('instansi', $institution));

        if ($hasProgressFilter) {
            $query->whereExists(function (QueryBuilder $query) use ($filters, $sectionCodes): void {
                $query->selectRaw('1')
                    ->from('curriculum_activity_progress as filtered_progress')
                    ->whereColumn('filtered_progress.user_id', 'users.id');
                $this->applyProgressFilters($query, $filters, $sectionCodes, 'filtered_progress');
            });
        }

        $learners = $query->orderBy('name')
            ->orderBy('id')
            ->paginate(self::LEARNERS_PER_PAGE)
            ->withQueryString();

        $users = $learners->getCollection();
        if ($users->isEmpty()) {
            return $learners;
        }

        $userIds = $users->pluck('id')->all();
        $progressRows = CurriculumActivityProgress::query()
            ->select([
                'id', 'user_id', 'package_name', 'content_version', 'activity_code', 'section_code',
                'legacy_status', 'viewed_at', 'started_at', 'attempted_at', 'self_checked_at',
                'completed_at', 'baseline_skipped_at', 'updated_at',
            ])
            ->whereIn('user_id', $userIds);
        $this->applyProgressFilters($progressRows->getQuery(), $filters, $sectionCodes, 'curriculum_activity_progress');
        $progressByUser = $progressRows->get()->groupBy('user_id');

        $attemptQuery = DB::table('curriculum_attempts as attempts')
            ->join('curriculum_activity_progress as matched_progress', function ($join): void {
                $join->on('matched_progress.user_id', '=', 'attempts.user_id')
                    ->on('matched_progress.package_name', '=', 'attempts.package_name')
                    ->on('matched_progress.content_version', '=', 'attempts.content_version')
                    ->on('matched_progress.activity_code', '=', 'attempts.activity_code');
            })
            ->whereIn('attempts.user_id', $userIds);
        $this->applyProgressFilters($attemptQuery, $filters, $sectionCodes, 'matched_progress');
        $attemptCounts = $attemptQuery
            ->selectRaw('attempts.user_id, COUNT(attempts.id) as attempt_count')
            ->groupBy('attempts.user_id')
            ->pluck('attempt_count', 'attempts.user_id');

        $overall = $this->progress->overallForUsers($users);
        $learners->setCollection($users->map(function (User $learner) use ($progressByUser, $attemptCounts, $overall): array {
            /** @var Collection<int, CurriculumActivityProgress> $rows */
            $rows = $progressByUser->get($learner->id, collect());

            return [
                'learner' => $learner,
                'summary' => $this->summarizeRows(
                    $rows,
                    (int) ($attemptCounts[$learner->id] ?? 0),
                    (int) ($overall[$learner->id] ?? 0),
                ),
            ];
        }));

        return $learners;
    }

    /** @return array<string, mixed> */
    public function aggregateOverview(): array
    {
        $totalLearners = User::query()->where('role', UserRole::Learner->value)->count();
        $progress = DB::table('curriculum_activity_progress as progress')
            ->join('users', 'users.id', '=', 'progress.user_id')
            ->where('users.role', UserRole::Learner->value)
            ->selectRaw('COUNT(progress.id) as tracked_activities')
            ->selectRaw('COUNT(DISTINCT progress.user_id) as active_learners')
            ->selectRaw('SUM(CASE WHEN progress.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_activities')
            ->selectRaw('MAX(progress.updated_at) as last_activity_at')
            ->first();
        $attemptCount = DB::table('curriculum_attempts as attempts')
            ->join('users', 'users.id', '=', 'attempts.user_id')
            ->where('users.role', UserRole::Learner->value)
            ->count();

        $activePackage = CurriculumPackage::active();
        $activeActivityCount = $activePackage?->entities()
            ->where('entity_type', 'activity')
            ->where('lifecycle_status', 'published')
            ->count() ?? 0;
        $activeCompletedCount = $activePackage === null ? 0 : DB::table('curriculum_activity_progress as progress')
            ->join('users', 'users.id', '=', 'progress.user_id')
            ->join('curriculum_entities as activity', function ($join) use ($activePackage): void {
                $join->on('activity.code', '=', 'progress.activity_code')
                    ->where('activity.curriculum_package_id', '=', $activePackage->id)
                    ->where('activity.entity_type', '=', 'activity')
                    ->where('activity.lifecycle_status', '=', 'published');
            })
            ->where('users.role', UserRole::Learner->value)
            ->where('progress.package_name', $activePackage->package_name)
            ->where('progress.content_version', $activePackage->content_version)
            ->whereNotNull('progress.completed_at')
            ->count();
        $activeDenominator = $totalLearners * $activeActivityCount;

        return [
            'total_learners' => $totalLearners,
            'active_learners' => (int) ($progress->active_learners ?? 0),
            'tracked_activities' => (int) ($progress->tracked_activities ?? 0),
            'completed_activities' => (int) ($progress->completed_activities ?? 0),
            'attempt_count' => $attemptCount,
            'last_activity_at' => $progress->last_activity_at ?? null,
            'active_package' => $activePackage,
            'active_activity_count' => $activeActivityCount,
            'active_completion_percent' => $activeDenominator === 0
                ? 0
                : (int) round(($activeCompletedCount / $activeDenominator) * 100),
        ];
    }

    /** @return array<string, Collection<int, mixed>> */
    public function filterOptions(): array
    {
        $packageVersions = CurriculumPackage::query()
            ->orderByDesc('is_active')
            ->orderByDesc('imported_at')
            ->get(['id', 'package_name', 'content_version', 'is_active', 'lifecycle_status']);
        $progressVersions = CurriculumActivityProgress::query()
            ->select(['package_name', 'content_version'])
            ->distinct()
            ->get();
        $versions = $packageVersions
            ->map(static fn (CurriculumPackage $package): array => [
                'package_name' => $package->package_name,
                'content_version' => $package->content_version,
                'is_active' => $package->is_active,
                'is_available' => true,
            ])
            ->concat($progressVersions->map(static fn (CurriculumActivityProgress $progress): array => [
                'package_name' => $progress->package_name,
                'content_version' => $progress->content_version,
                'is_active' => false,
                'is_available' => false,
            ]))
            ->unique(static fn (array $row): string => $row['package_name'].'|'.$row['content_version'])
            ->values();

        $modules = CurriculumEntity::query()
            ->with('package:id,package_name,content_version,is_active')
            ->where('entity_type', 'chapter')
            ->get(['id', 'curriculum_package_id', 'code', 'position', 'payload'])
            ->sortByDesc(static fn (CurriculumEntity $chapter): bool => (bool) $chapter->package?->is_active)
            ->unique('code')
            ->map(static fn (CurriculumEntity $chapter): array => [
                'code' => $chapter->code,
                'module' => (int) ($chapter->payload['module'] ?? 0),
                'title' => (string) ($chapter->payload['title'] ?? $chapter->code),
            ])
            ->sortBy('module')
            ->values();

        return [
            'institutions' => User::query()
                ->where('role', UserRole::Learner->value)
                ->whereNotNull('instansi')
                ->where('instansi', '!=', '')
                ->distinct()
                ->orderBy('instansi')
                ->pluck('instansi'),
            'versions' => $versions,
            'modules' => $modules,
        ];
    }

    /**
     * Build one learner's metadata-only hierarchy. Private response rows,
     * confidence values, and media are intentionally outside every query.
     *
     * @return array<string, mixed>
     */
    public function learnerDetail(
        User $learner,
        ?string $packageName,
        ?string $contentVersion,
        ?int $institutionMembershipId = null,
    ): array {
        $inventory = $this->versionInventoryFor($learner, $institutionMembershipId);
        [$packageName, $contentVersion] = $this->resolveSelection(
            $learner,
            $inventory,
            $packageName,
            $contentVersion,
        );

        $package = $packageName !== null && $contentVersion !== null
            ? CurriculumPackage::query()
                ->where('package_name', $packageName)
                ->where('content_version', $contentVersion)
                ->first()
            : null;

        $progressRows = $packageName !== null && $contentVersion !== null
            ? CurriculumActivityProgress::query()
                ->select([
                    'id', 'user_id', 'package_name', 'content_version', 'activity_code', 'section_code',
                    'legacy_status', 'viewed_at', 'started_at', 'attempted_at', 'self_checked_at',
                    'completed_at', 'baseline_skipped_at', 'updated_at',
                ])
                ->where('user_id', $learner->id)
                ->when($institutionMembershipId !== null, fn ($query) => $query->where('institution_membership_id', $institutionMembershipId))
                ->where('package_name', $packageName)
                ->where('content_version', $contentVersion)
                ->get()
            : collect();
        $progressByActivity = $progressRows->keyBy('activity_code');

        $attemptCounts = $packageName !== null && $contentVersion !== null
            ? DB::table('curriculum_attempts')
                ->where('user_id', $learner->id)
                ->when($institutionMembershipId !== null, fn ($query) => $query->where('institution_membership_id', $institutionMembershipId))
                ->where('package_name', $packageName)
                ->where('content_version', $contentVersion)
                ->selectRaw('activity_code, COUNT(id) as attempt_count, MAX(created_at) as last_attempt_at')
                ->groupBy('activity_code')
                ->get()
                ->keyBy('activity_code')
            : collect();

        $entities = $package?->entities()
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity'])
            ->get(['id', 'curriculum_package_id', 'code', 'entity_type', 'parent_code', 'position', 'lifecycle_status', 'payload'])
            ?? collect();
        $chapters = $entities->where('entity_type', 'chapter')
            ->sortBy(static fn (CurriculumEntity $chapter): string => sprintf(
                '%08d|%08d|%s',
                (int) ($chapter->payload['module'] ?? PHP_INT_MAX),
                (int) ($chapter->position ?? PHP_INT_MAX),
                $chapter->code,
            ));
        $sectionsByChapter = $entities->where('entity_type', 'lesson-section')->groupBy('parent_code');
        $activitiesBySection = $entities->where('entity_type', 'activity')->groupBy('parent_code');

        $knownActivityCodes = collect();
        $hierarchy = $chapters->map(function (CurriculumEntity $chapter) use (
            $sectionsByChapter,
            $activitiesBySection,
            $progressByActivity,
            $attemptCounts,
            $knownActivityCodes,
        ): array {
            $sections = ($sectionsByChapter[$chapter->code] ?? collect())
                ->sortBy(static fn (CurriculumEntity $section): string => sprintf(
                    '%08d|%s',
                    (int) ($section->position ?? PHP_INT_MAX),
                    $section->code,
                ))
                ->map(function (CurriculumEntity $section) use ($activitiesBySection, $progressByActivity, $attemptCounts, $knownActivityCodes): array {
                    $activities = ($activitiesBySection[$section->code] ?? collect())
                        ->sortBy(static fn (CurriculumEntity $activity): string => sprintf(
                            '%08d|%s',
                            (int) ($activity->position ?? PHP_INT_MAX),
                            $activity->code,
                        ))
                        ->map(function (CurriculumEntity $activity) use ($progressByActivity, $attemptCounts, $knownActivityCodes): array {
                            $knownActivityCodes->push($activity->code);
                            $progress = $progressByActivity->get($activity->code);

                            return $this->activityMetadata(
                                $activity->code,
                                (string) ($activity->payload['title'] ?? $activity->code),
                                $activity->lifecycle_status,
                                $progress,
                                (int) ($attemptCounts[$activity->code]->attempt_count ?? 0),
                            );
                        })->values();

                    return [
                        'code' => $section->code,
                        'title' => (string) ($section->payload['title'] ?? $section->code),
                        'lifecycle_status' => $section->lifecycle_status,
                        'activities' => $activities,
                    ];
                })->values();

            return [
                'code' => $chapter->code,
                'module' => (int) ($chapter->payload['module'] ?? 0),
                'title' => (string) ($chapter->payload['title'] ?? $chapter->code),
                'lifecycle_status' => $chapter->lifecycle_status,
                'sections' => $sections,
            ];
        })->values();

        $unresolved = $progressRows
            ->reject(static fn (CurriculumActivityProgress $progress): bool => $knownActivityCodes->contains($progress->activity_code))
            ->map(fn (CurriculumActivityProgress $progress): array => array_merge(
                $this->activityMetadata(
                    $progress->activity_code,
                    $progress->activity_code,
                    null,
                    $progress,
                    (int) ($attemptCounts[$progress->activity_code]->attempt_count ?? 0),
                ),
                ['section_code' => $progress->section_code],
            ))
            ->values();

        $knownActivities = $hierarchy->flatMap(static fn (array $chapter): Collection => collect($chapter['sections']))
            ->flatMap(static fn (array $section): Collection => $section['activities']);
        $knownCount = $knownActivities->count();
        $completedKnown = $knownActivities->where('state', 'completed')->count();

        return [
            'learner' => $learner,
            'inventory' => $inventory,
            'selection' => [
                'package_name' => $packageName,
                'content_version' => $contentVersion,
                'package' => $package,
                'is_available' => $package !== null,
            ],
            'hierarchy' => $hierarchy,
            'unresolved' => $unresolved,
            'summary' => [
                'known_activities' => $knownCount,
                'tracked_activities' => $progressRows->count(),
                'completed_activities' => $progressRows->whereNotNull('completed_at')->count(),
                'attempt_count' => (int) $attemptCounts->sum('attempt_count'),
                'completion_percent' => $knownCount === 0 ? 0 : (int) round(($completedKnown / $knownCount) * 100),
                'last_activity_at' => $progressRows->max('updated_at'),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function sectionCodesForModule(array $filters): ?array
    {
        if (! filled($filters['module'] ?? null)) {
            return null;
        }

        return CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('parent_code', $filters['module'])
            ->when($filters['version'] ?? null, function (Builder $query, string $version): void {
                $query->whereHas('package', static fn (Builder $package): Builder => $package->where('content_version', $version));
            })
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>|null  $sectionCodes
     */
    private function applyProgressFilters(QueryBuilder $query, array $filters, ?array $sectionCodes, string $alias): void
    {
        if (filled($filters['version'] ?? null)) {
            $query->where("{$alias}.content_version", $filters['version']);
        }

        if ($sectionCodes !== null) {
            $sectionCodes === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn("{$alias}.section_code", $sectionCodes);
        }

        if (filled($filters['status'] ?? null)) {
            $this->applyExactState($query, (string) $filters['status'], $alias);
        }

        if (filled($filters['recent'] ?? null)) {
            $query->where("{$alias}.updated_at", '>=', now()->subDays((int) $filters['recent']));
        }
    }

    private function applyExactState(QueryBuilder $query, string $state, string $alias): void
    {
        $column = self::STATE_COLUMNS[$state];
        $query->whereNotNull("{$alias}.{$column}");

        $higherStates = array_slice(
            array_keys(self::STATE_COLUMNS),
            array_search($state, array_keys(self::STATE_COLUMNS), true) + 1,
        );
        foreach ($higherStates as $higherState) {
            $query->whereNull("{$alias}.".self::STATE_COLUMNS[$higherState]);
        }
    }

    /** @return array<string, mixed> */
    private function summarizeRows(Collection $rows, int $attemptCount, int $overall): array
    {
        $stateCounts = array_fill_keys(array_merge(['not_started'], array_keys(self::STATE_COLUMNS)), 0);
        foreach ($rows as $row) {
            $stateCounts[$this->stateOf($row)]++;
        }
        $latest = $rows->sortByDesc('updated_at')->first();

        return [
            'tracked_activities' => $rows->count(),
            'completed_activities' => $rows->whereNotNull('completed_at')->count(),
            'attempt_count' => $attemptCount,
            'overall_percent' => $overall,
            'latest_state' => $latest === null ? 'not_started' : $this->stateOf($latest),
            'last_activity_at' => $latest?->updated_at,
            'state_counts' => $stateCounts,
            'versions' => $rows->map(static fn (CurriculumActivityProgress $row): string => $row->package_name.' '.$row->content_version)
                ->unique()->values(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function versionInventoryFor(User $learner, ?int $institutionMembershipId = null): Collection
    {
        $packages = CurriculumPackage::query()
            ->orderByDesc('is_active')
            ->orderByDesc('imported_at')
            ->get(['id', 'package_name', 'content_version', 'lifecycle_status', 'is_active', 'imported_at'])
            ->map(static fn (CurriculumPackage $package): array => [
                'package_name' => $package->package_name,
                'content_version' => $package->content_version,
                'lifecycle_status' => $package->lifecycle_status,
                'is_active' => $package->is_active,
                'is_available' => true,
                'last_activity_at' => null,
            ]);
        $progress = CurriculumActivityProgress::query()
            ->where('user_id', $learner->id)
            ->when($institutionMembershipId !== null, fn ($query) => $query->where('institution_membership_id', $institutionMembershipId))
            ->selectRaw('package_name, content_version, MAX(updated_at) as last_activity_at')
            ->groupBy('package_name', 'content_version')
            ->get()
            ->map(static fn (CurriculumActivityProgress $row): array => [
                'package_name' => $row->package_name,
                'content_version' => $row->content_version,
                'lifecycle_status' => null,
                'is_active' => false,
                'is_available' => false,
                'last_activity_at' => $row->getRawOriginal('last_activity_at'),
            ]);

        return $packages->concat($progress)
            ->groupBy(static fn (array $row): string => $row['package_name'].'|'.$row['content_version'])
            ->map(static function (Collection $rows): array {
                $available = $rows->firstWhere('is_available', true);
                $progress = $rows->firstWhere('last_activity_at', '!=', null);

                return array_merge($available ?? $rows->first(), [
                    'last_activity_at' => $progress['last_activity_at'] ?? null,
                ]);
            })
            ->sortByDesc(static fn (array $row): string => sprintf(
                '%d|%s',
                $row['is_active'] ? 1 : 0,
                $row['last_activity_at'] ?? '',
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $inventory
     * @return array{?string, ?string}
     */
    private function resolveSelection(
        User $learner,
        Collection $inventory,
        ?string $packageName,
        ?string $contentVersion,
    ): array {
        if ($contentVersion !== null) {
            if ($packageName !== null) {
                return [$packageName, $contentVersion];
            }

            $match = $inventory->firstWhere('content_version', $contentVersion);

            return [$match['package_name'] ?? null, $contentVersion];
        }

        $active = $inventory->firstWhere('is_active', true);
        $selected = $active ?? $inventory->first();

        return [$selected['package_name'] ?? null, $selected['content_version'] ?? null];
    }

    /** @return array<string, mixed> */
    private function activityMetadata(
        string $code,
        string $title,
        ?string $lifecycleStatus,
        ?CurriculumActivityProgress $progress,
        int $attemptCount,
    ): array {
        return [
            'code' => $code,
            'title' => $title,
            'lifecycle_status' => $lifecycleStatus,
            'state' => $progress === null ? 'not_started' : $this->stateOf($progress),
            'attempt_count' => $attemptCount,
            'last_activity_at' => $progress?->updated_at,
            'completed_at' => $progress?->completed_at,
            'legacy_status' => $progress?->legacy_status,
            'baseline_skipped_at' => $progress?->baseline_skipped_at,
        ];
    }

    private function stateOf(CurriculumActivityProgress $progress): string
    {
        return match (true) {
            $progress->completed_at !== null => 'completed',
            $progress->self_checked_at !== null => 'self_checked',
            $progress->attempted_at !== null => 'attempted',
            $progress->started_at !== null => 'started',
            $progress->viewed_at !== null => 'viewed',
            default => 'not_started',
        };
    }
}
