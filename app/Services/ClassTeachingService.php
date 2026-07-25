<?php

namespace App\Services;

use App\Enums\CourseOfferingStatus;
use App\Models\ClassAnnouncement;
use App\Models\CourseOffering;
use App\Models\CourseOfferingRevisionEvent;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ClassTeachingService
{
    public function __construct(
        private readonly CourseAccessService $access,
        private readonly CourseRevisionService $revisions,
    ) {}

    public function canReviseContent(User $actor, CourseOffering $offering): bool
    {
        return $this->access->canManageOffering($actor, $offering)
            && in_array($offering->status, [CourseOfferingStatus::Draft, CourseOfferingStatus::EnrollmentOpen], true)
            && ! $this->hasLearningActivity($offering);
    }

    /**
     * @param  list<int|string>  $moduleIds
     */
    public function reviseContent(
        User $actor,
        CourseOffering $offering,
        CurriculumPackage $package,
        array $moduleIds,
        string $title,
        string $reason,
    ): CourseRevision {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A reason of 1 to 500 characters is required.');
        }

        return DB::transaction(function () use ($actor, $offering, $package, $moduleIds, $title, $reason): CourseRevision {
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $this->access->authorizeOfferingManagement($actor, $lockedOffering);

            if (! in_array($lockedOffering->status, [CourseOfferingStatus::Draft, CourseOfferingStatus::EnrollmentOpen], true)) {
                throw new RuntimeException('Class modules can change only before the Class becomes active.');
            }
            if ($this->hasLearningActivity($lockedOffering)) {
                throw new RuntimeException('Class modules cannot change after learner activity has been recorded.');
            }

            $currentRevision = CourseRevision::query()
                ->with('modules')
                ->lockForUpdate()
                ->findOrFail($lockedOffering->course_revision_id);
            $normalizedIds = array_map(static fn (int|string $id): string => (string) $id, $moduleIds);
            $currentIds = $currentRevision->modules
                ->pluck('curriculum_entity_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all();
            if ($normalizedIds === $currentIds) {
                throw new InvalidArgumentException('Select a different set or order of modules before saving a new version.');
            }

            $revision = $this->revisions->createForOffering(
                $actor,
                $lockedOffering,
                $package,
                $moduleIds,
                $title,
            );

            $fromRevisionId = (string) $lockedOffering->course_revision_id;
            $lockedOffering->forceFill(['course_revision_id' => $revision->getKey()])->saveQuietly();
            CourseOfferingRevisionEvent::query()->create([
                'course_offering_id' => $lockedOffering->getKey(),
                'institution_id' => $lockedOffering->institution_id,
                'from_course_revision_id' => $fromRevisionId,
                'to_course_revision_id' => $revision->getKey(),
                'actor_user_id' => $actor->getKey(),
                'reason' => $reason,
            ]);

            return $revision;
        }, 3);
    }

    public function publishAnnouncement(
        User $actor,
        CourseOffering $offering,
        string $title,
        string $body,
        int|string|null $moduleId = null,
    ): ClassAnnouncement {
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('An instruction title of 1 to 180 characters is required.');
        }
        if ($body === '' || mb_strlen($body) > 5000) {
            throw new InvalidArgumentException('Instruction text must contain 1 to 5000 characters.');
        }

        return DB::transaction(function () use ($actor, $offering, $title, $body, $moduleId): ClassAnnouncement {
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $this->access->authorizeOfferingManagement($actor, $lockedOffering);
            if (in_array($lockedOffering->status, [CourseOfferingStatus::Closed, CourseOfferingStatus::Archived], true)) {
                throw new RuntimeException('New instructions cannot be posted to a closed or archived Class.');
            }

            $revision = CourseRevision::query()->findOrFail($lockedOffering->course_revision_id);
            $module = null;
            if ($moduleId !== null && $moduleId !== '') {
                $module = CurriculumEntity::query()
                    ->whereKey($moduleId)
                    ->where('curriculum_package_id', $revision->curriculum_package_id)
                    ->where('entity_type', 'chapter')
                    ->whereHas('courseRevisionModules', fn ($query) => $query
                        ->where('course_revision_id', $revision->getKey()))
                    ->first();
                if (! $module instanceof CurriculumEntity) {
                    throw new InvalidArgumentException('Choose a module that belongs to this Class.');
                }
            }

            return ClassAnnouncement::query()->create([
                'course_offering_id' => $lockedOffering->getKey(),
                'institution_id' => $lockedOffering->institution_id,
                'curriculum_package_id' => $module?->curriculum_package_id,
                'curriculum_entity_id' => $module?->getKey(),
                'title' => $title,
                'body' => $body,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
                'published_at' => now(),
            ]);
        }, 3);
    }

    public function archiveAnnouncement(
        User $actor,
        CourseOffering $offering,
        ClassAnnouncement $announcement,
        int $expectedRevision,
    ): ClassAnnouncement {
        return DB::transaction(function () use ($actor, $offering, $announcement, $expectedRevision): ClassAnnouncement {
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $this->access->authorizeOfferingManagement($actor, $lockedOffering);
            $locked = ClassAnnouncement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            if ($locked->course_offering_id !== $lockedOffering->getKey()) {
                throw new InvalidArgumentException('The instruction does not belong to this Class.');
            }
            if ($locked->revision !== $expectedRevision) {
                throw new RuntimeException('This instruction changed before your request. Reload the Class and try again.');
            }
            if ($locked->archived_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'archived_at' => now(),
                'updated_by_user_id' => $actor->getKey(),
                'revision' => $locked->revision + 1,
            ])->save();

            return $locked;
        }, 3);
    }

    private function hasLearningActivity(CourseOffering $offering): bool
    {
        foreach (['curriculum_activity_progress', 'curriculum_attempts', 'completions', 'learner_text_responses'] as $table) {
            if (DB::table($table)->where('course_offering_id', $offering->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }
}
