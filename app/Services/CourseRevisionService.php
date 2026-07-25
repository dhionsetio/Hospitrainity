<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CourseRevisionModule;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\Curriculum\CurriculumReleaseGuard;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class CourseRevisionService
{
    public function __construct(
        private readonly CourseAccessService $access,
        private readonly CurriculumReleaseGuard $releaseGuard,
    ) {}

    /**
     * @param  list<int|string>  $curriculumEntityIds
     *
     * @throws JsonException
     */
    public function create(
        User $actor,
        Course $course,
        CurriculumPackage $package,
        array $curriculumEntityIds,
        string $title,
    ): CourseRevision {
        $this->access->authorizeCourseManagement($actor, $course);

        return $this->createAuthorized(
            $actor,
            $course,
            $package,
            $curriculumEntityIds,
            $title,
            fn (Course $lockedCourse) => $this->access->authorizeCourseManagement($actor, $lockedCourse),
        );
    }

    /**
     * Create a new immutable revision for a Class the actor is assigned to.
     * This does not grant permission over another Class that uses the Course.
     *
     * @param  list<int|string>  $curriculumEntityIds
     *
     * @throws JsonException
     */
    public function createForOffering(
        User $actor,
        CourseOffering $offering,
        CurriculumPackage $package,
        array $curriculumEntityIds,
        string $title,
    ): CourseRevision {
        $this->access->authorizeOfferingManagement($actor, $offering);
        $course = Course::query()->findOrFail($offering->course_id);

        return $this->createAuthorized(
            $actor,
            $course,
            $package,
            $curriculumEntityIds,
            $title,
            function (Course $lockedCourse) use ($actor, $offering): void {
                $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
                if ($lockedOffering->course_id !== $lockedCourse->getKey()) {
                    throw new RuntimeException('The Class and Course changed before the content revision could be created.');
                }
                $this->access->authorizeOfferingManagement($actor, $lockedOffering);
            },
        );
    }

    /**
     * @param  list<int|string>  $curriculumEntityIds
     */
    private function createAuthorized(
        User $actor,
        Course $course,
        CurriculumPackage $package,
        array $curriculumEntityIds,
        string $title,
        Closure $authorize,
    ): CourseRevision {

        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 180) {
            throw new InvalidArgumentException('Course revision title must contain 1 to 180 characters.');
        }

        $entityIds = array_values(array_unique(array_map(static fn (int|string $id): string => (string) $id, $curriculumEntityIds)));
        if ($entityIds === [] || count($entityIds) !== count($curriculumEntityIds)) {
            throw new InvalidArgumentException('Select one or more unique canonical modules in the intended order.');
        }

        return DB::transaction(function () use ($actor, $course, $package, $entityIds, $title, $authorize): CourseRevision {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            $lockedPackage = CurriculumPackage::query()->lockForUpdate()->findOrFail($package->getKey());
            $authorize($lockedCourse);

            if (! $lockedPackage->is_active || strtolower((string) $lockedPackage->lifecycle_status) !== 'published') {
                throw new RuntimeException('Course revisions may only select modules from the active published canonical package.');
            }
            $this->releaseGuard->assertDeliverable($lockedPackage);

            $entitiesById = CurriculumEntity::query()
                ->where('curriculum_package_id', $lockedPackage->getKey())
                ->where('entity_type', 'chapter')
                ->where('lifecycle_status', 'published')
                ->whereKey($entityIds)
                ->get()
                ->keyBy(fn (CurriculumEntity $entity): string => (string) $entity->getKey());

            $orderedEntities = [];
            foreach ($entityIds as $entityId) {
                $entity = $entitiesById->get($entityId);
                if (! $entity instanceof CurriculumEntity) {
                    throw new InvalidArgumentException('Every selected module must be published in the chosen canonical package.');
                }
                $orderedEntities[] = $entity;
            }

            $revisionNumber = ((int) CourseRevision::query()
                ->where('course_id', $lockedCourse->getKey())
                ->lockForUpdate()
                ->max('revision_number')) + 1;

            $content = [
                'curriculum_package_id' => (string) $lockedPackage->getKey(),
                'curriculum_source_tree_sha256' => (string) $lockedPackage->source_tree_sha256,
                'modules' => array_map(static fn (CurriculumEntity $entity): array => [
                    'entity_uuid' => (string) $entity->entity_uuid,
                    'code' => (string) $entity->code,
                    'source_sha256' => (string) $entity->source_sha256,
                ], $orderedEntities),
            ];

            $revision = CourseRevision::query()->create([
                'course_id' => $lockedCourse->getKey(),
                'curriculum_package_id' => $lockedPackage->getKey(),
                'revision_number' => $revisionNumber,
                'title' => $title,
                'content_sha256' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'created_by_user_id' => $actor->getKey(),
            ]);

            foreach ($orderedEntities as $index => $entity) {
                CourseRevisionModule::query()->create([
                    'course_revision_id' => $revision->getKey(),
                    'curriculum_package_id' => $lockedPackage->getKey(),
                    'curriculum_entity_id' => $entity->getKey(),
                    'position' => $index + 1,
                ]);
            }

            return $revision->load('modules.curriculumEntity');
        });
    }
}
