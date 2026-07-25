<?php

namespace App\Services;

use App\Enums\CourseOfferingStatus;
use App\Enums\TeachingAssignmentRole;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ClassWorkspaceService
{
    public function __construct(
        private readonly CourseAccessService $access,
        private readonly CourseRevisionService $revisions,
        private readonly TeachingAssignmentService $assignments,
    ) {}

    /**
     * @param  list<int|string>  $moduleIds
     */
    public function createWithCourse(
        User $actor,
        Institution $institution,
        CurriculumPackage $package,
        InstitutionMembership $primaryInstructor,
        string $courseKey,
        string $courseTitle,
        ?string $courseDescription,
        string $revisionTitle,
        array $moduleIds,
        string $classKey,
        string $classTitle,
        ?string $termLabel,
        ?string $timezone,
    ): CourseOffering {
        return DB::transaction(function () use (
            $actor,
            $institution,
            $package,
            $primaryInstructor,
            $courseKey,
            $courseTitle,
            $courseDescription,
            $revisionTitle,
            $moduleIds,
            $classKey,
            $classTitle,
            $termLabel,
            $timezone,
        ): CourseOffering {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedInstitution = Institution::query()->lockForUpdate()->findOrFail($institution->getKey());
            $lockedPackage = CurriculumPackage::query()->lockForUpdate()->findOrFail($package->getKey());
            if (! $this->access->canCreateCourse($lockedActor, $lockedInstitution)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if (! $lockedPackage->is_active || strtolower((string) $lockedPackage->lifecycle_status) !== 'published') {
                throw new RuntimeException('A new Course must use the active published canonical package.');
            }

            $course = Course::query()->create([
                'institution_id' => $lockedInstitution->getKey(),
                'key' => $this->required($courseKey, 100, 'Course key'),
                'title' => $this->required($courseTitle, 180, 'Course title'),
                'description' => $this->optional($courseDescription, 2_000, 'Course description'),
                'created_by_user_id' => $lockedActor->getKey(),
            ]);
            $revision = $this->revisions->create(
                $lockedActor,
                $course,
                $lockedPackage,
                $moduleIds,
                $revisionTitle,
            );

            return $this->createOffering(
                $lockedActor,
                $lockedInstitution,
                $revision,
                $primaryInstructor,
                $classKey,
                $classTitle,
                $termLabel,
                $timezone,
            );
        }, attempts: 3);
    }

    public function createFromRevision(
        User $actor,
        Institution $institution,
        CourseRevision $revision,
        InstitutionMembership $primaryInstructor,
        string $classKey,
        string $classTitle,
        ?string $termLabel,
        ?string $timezone,
    ): CourseOffering {
        return DB::transaction(function () use (
            $actor,
            $institution,
            $revision,
            $primaryInstructor,
            $classKey,
            $classTitle,
            $termLabel,
            $timezone,
        ): CourseOffering {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedInstitution = Institution::query()->lockForUpdate()->findOrFail($institution->getKey());
            $lockedRevision = CourseRevision::query()->lockForUpdate()->findOrFail($revision->getKey());
            $course = Course::query()->lockForUpdate()->findOrFail($lockedRevision->course_id);
            if ($course->institution_id !== $lockedInstitution->getKey()
                || ! $this->access->canCreateOffering($lockedActor, $course)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            return $this->createOffering(
                $lockedActor,
                $lockedInstitution,
                $lockedRevision,
                $primaryInstructor,
                $classKey,
                $classTitle,
                $termLabel,
                $timezone,
            );
        }, attempts: 3);
    }

    public function update(
        User $actor,
        CourseOffering $offering,
        string $title,
        ?string $termLabel,
        ?string $timezone,
    ): CourseOffering {
        return DB::transaction(function () use ($actor, $offering, $title, $termLabel, $timezone): CourseOffering {
            $locked = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            if (! $this->access->canManageOffering($lockedActor, $locked)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $locked->update([
                'title' => $this->required($title, 180, 'Class title'),
                'term_label' => $this->optional($termLabel, 120, 'Term label'),
                'timezone' => $timezone,
            ]);

            return $locked->fresh();
        }, attempts: 3);
    }

    public function copy(
        User $actor,
        CourseOffering $source,
        InstitutionMembership $primaryInstructor,
        string $key,
        string $title,
    ): CourseOffering {
        return DB::transaction(function () use ($actor, $source, $primaryInstructor, $key, $title): CourseOffering {
            $locked = CourseOffering::query()->lockForUpdate()->findOrFail($source->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $institution = Institution::query()->lockForUpdate()->findOrFail($locked->institution_id);
            $revision = CourseRevision::query()->lockForUpdate()->findOrFail($locked->course_revision_id);
            if (! $this->access->canManageOffering($lockedActor, $locked)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            return $this->createOffering(
                $lockedActor,
                $institution,
                $revision,
                $primaryInstructor,
                $key,
                $title,
                null,
                $locked->timezone,
            );
        }, attempts: 3);
    }

    private function createOffering(
        User $actor,
        Institution $institution,
        CourseRevision $revision,
        InstitutionMembership $primaryInstructor,
        string $key,
        string $title,
        ?string $termLabel,
        ?string $timezone,
    ): CourseOffering {
        $course = Course::query()->lockForUpdate()->findOrFail($revision->course_id);
        if ($course->institution_id !== $institution->getKey()
            || ! $this->access->canCreateOffering($actor, $course)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        $offering = CourseOffering::query()->create([
            'institution_id' => $institution->getKey(),
            'course_id' => $course->getKey(),
            'course_revision_id' => $revision->getKey(),
            'key' => $this->required($key, 100, 'Class key'),
            'title' => $this->required($title, 180, 'Class title'),
            'term_label' => $this->optional($termLabel, 120, 'Term label'),
            'status' => CourseOfferingStatus::Draft,
            'timezone' => $timezone,
            'created_by_user_id' => $actor->getKey(),
        ]);
        $this->assignments->assign(
            $actor,
            $offering,
            $primaryInstructor,
            TeachingAssignmentRole::Primary,
        );

        return $offering->load(['course', 'revision', 'teachingAssignments.membership.user']);
    }

    private function required(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} must contain 1 to {$max} characters.");
        }

        return $value;
    }

    private function optional(?string $value, int $max, string $label): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->required($value, $max, $label);
    }
}
