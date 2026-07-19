<?php

namespace App\Services;

use App\Models\Completion;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Collection;

class CurriculumProgressService
{
    public function __construct(private readonly CanonicalCurriculumRepository $canonicalCurriculum) {}

    /**
     * Load the published curriculum once in deterministic display order.
     *
     * @return Collection<int, Module>
     */
    public function publishedModules(bool $withLessonCount = false): Collection
    {
        return Module::query()
            ->where('is_published', true)
            ->curriculumOrder()
            ->when($withLessonCount, static fn ($query) => $query->withCount('lessons'))
            ->with(array_map(
                static fn (string $relation): string => "lessons.{$relation}",
                Lesson::PROGRESS_RELATIONS,
            ))
            ->get();
    }

    /**
     * Attach per-module progress for one learner without any lazy queries.
     *
     * @return Collection<int, Module>
     */
    public function dashboardModulesFor(User $user): Collection
    {
        $modules = $this->publishedModules(withLessonCount: true);
        $completedKeys = Completion::completedKeysFor(
            $user,
            Lesson::mergeIdsByType($modules->flatMap->lessons),
        );

        foreach ($modules as $module) {
            $module->progress = $this->moduleProgress($module, $completedKeys);
        }

        return $modules;
    }

    /**
     * Compute overall progress for a displayed learner page in one completion query.
     *
     * @param  Collection<int, User>  $users
     * @return array<int|string, int>
     */
    public function overallForUsers(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        if ($this->canonicalCurriculum->isActive()) {
            return $this->canonicalCurriculum->overallForUsers($users);
        }

        $modules = $this->publishedModules();
        if ($modules->isEmpty()) {
            return $users->mapWithKeys(fn (User $user): array => [$user->getKey() => 0])->all();
        }

        $idsByType = Lesson::mergeIdsByType($modules->flatMap->lessons);
        $completedByUser = Completion::completedKeysForUsers($users, $idsByType);

        return $users->mapWithKeys(function (User $user) use ($completedByUser, $modules): array {
            return [
                $user->getKey() => $this->overallProgress(
                    $modules,
                    $completedByUser[$user->getKey()] ?? [],
                ),
            ];
        })->all();
    }

    public function overallForUser(User $user): int
    {
        return $this->overallForUsers(collect([$user]))[$user->getKey()] ?? 0;
    }

    /** @param array<string, true> $completedKeys */
    private function moduleProgress(Module $module, array $completedKeys): int|float
    {
        if ($module->lessons->isEmpty()) {
            return 0;
        }

        return $module->lessons
            ->map(fn (Lesson $lesson): int => $lesson->progressFromKeys($completedKeys))
            ->average();
    }

    /**
     * Preserve the established product calculation: rounded module averages,
     * then a rounded average across every published module (including empty ones).
     *
     * @param  Collection<int, Module>  $modules
     * @param  array<string, true>  $completedKeys
     */
    private function overallProgress(Collection $modules, array $completedKeys): int
    {
        if ($modules->isEmpty()) {
            return 0;
        }

        $total = $modules->sum(function (Module $module) use ($completedKeys): int {
            return (int) round($this->moduleProgress($module, $completedKeys));
        });

        return (int) round($total / $modules->count());
    }
}
