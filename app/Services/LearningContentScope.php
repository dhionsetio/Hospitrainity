<?php

namespace App\Services;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\Curriculum\CurriculumReleaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use RuntimeException;

final class LearningContentScope
{
    public function __construct(
        private readonly LearningContext $learningContext,
        private readonly CurriculumReleaseGuard $releaseGuard,
    ) {}

    /**
     * @return array{
     *     package: CurriculumPackage|null,
     *     context: array<string, int|string|bool|null>,
     *     allowed_chapter_codes: list<string>|null
     * }
     */
    public function current(Request $request, User $user): array
    {
        $context = $this->learningContext->current($request, $user);
        if ($context['class_invalidated']) {
            throw new AuthorizationException(__('Your selected Class is no longer available. Choose another learning context.'));
        }

        if ($context['course_enrollment_id'] === null) {
            return [
                'package' => CurriculumPackage::active(),
                'context' => $context,
                'allowed_chapter_codes' => null,
            ];
        }

        $enrollment = CourseEnrollment::query()
            ->whereKey($context['course_enrollment_id'])
            ->where('institution_membership_id', $context['membership_id'])
            ->where('course_offering_id', $context['course_offering_id'])
            ->where('status', CourseEnrollmentStatus::Active->value)
            ->whereHas('offering', static fn ($query) => $query->where('status', CourseOfferingStatus::Active->value))
            ->whereHas('membership', static function ($query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value)
                    ->whereHas('roleAssignments', static function ($roles): void {
                        $roles->where('role', InstitutionRole::Learner->value)->whereNull('revoked_at');
                    });
            })
            ->first();
        if ($enrollment === null) {
            throw new AuthorizationException(__('Your selected Class is no longer available. Choose another learning context.'));
        }

        $offering = CourseOffering::query()
            ->whereKey($enrollment->course_offering_id)
            ->where('status', CourseOfferingStatus::Active->value)
            ->first();
        $revision = $offering === null
            ? null
            : CourseRevision::query()->find($offering->course_revision_id);
        $package = $revision === null
            ? null
            : CurriculumPackage::query()->with('release')->find($revision->curriculum_package_id);
        if ($offering === null || $revision === null || $package === null) {
            throw new RuntimeException('Class delivery refused: the pinned Course Revision is incomplete.');
        }
        $this->releaseGuard->assertPinnedDeliverable($package);

        $chapterCodes = CurriculumEntity::query()
            ->where('curriculum_entities.curriculum_package_id', $package->getKey())
            ->where('curriculum_entities.entity_type', 'chapter')
            ->where('curriculum_entities.lifecycle_status', 'published')
            ->whereHas('courseRevisionModules', static fn ($query) => $query
                ->where('course_revision_modules.course_revision_id', $revision->getKey())
                ->where('course_revision_modules.curriculum_package_id', $package->getKey()))
            ->join('course_revision_modules', function ($join) use ($revision): void {
                $join->on('course_revision_modules.curriculum_entity_id', '=', 'curriculum_entities.id')
                    ->where('course_revision_modules.course_revision_id', $revision->getKey());
            })
            ->orderBy('course_revision_modules.position')
            ->pluck('curriculum_entities.code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
        if ($chapterCodes === []) {
            throw new RuntimeException('Class delivery refused: the pinned Course Revision has no modules.');
        }

        return [
            'package' => $package,
            'context' => $context,
            'allowed_chapter_codes' => $chapterCodes,
        ];
    }

    /** @param array{package: CurriculumPackage|null, context: array<string, mixed>, allowed_chapter_codes: list<string>|null} $scope */
    public function allowsEntity(array $scope, CurriculumEntity $entity): bool
    {
        $package = $scope['package'];
        if ($package === null || (int) $entity->curriculum_package_id !== (int) $package->getKey()) {
            return false;
        }
        $allowed = $scope['allowed_chapter_codes'];
        if ($allowed === null) {
            return true;
        }

        $chapterCode = match ($entity->entity_type) {
            'chapter' => (string) $entity->code,
            'lesson-section' => (string) $entity->parent_code,
            'activity' => $this->chapterCodeForSection($package, (string) $entity->parent_code),
            'prompt-item', 'rubric' => $this->chapterCodeForActivity($package, (string) $entity->parent_code),
            'answer-model', 'feedback-model' => $this->chapterCodeForPrompt($package, (string) $entity->parent_code),
            default => null,
        };

        return $chapterCode !== null && in_array($chapterCode, $allowed, true);
    }

    private function chapterCodeForSection(CurriculumPackage $package, string $sectionCode): ?string
    {
        return CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'lesson-section')
            ->where('code', $sectionCode)
            ->value('parent_code');
    }

    private function chapterCodeForActivity(CurriculumPackage $package, string $activityCode): ?string
    {
        $sectionCode = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'activity')
            ->where('code', $activityCode)
            ->value('parent_code');

        return is_string($sectionCode) ? $this->chapterCodeForSection($package, $sectionCode) : null;
    }

    private function chapterCodeForPrompt(CurriculumPackage $package, string $promptCode): ?string
    {
        $activityCode = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'prompt-item')
            ->where('code', $promptCode)
            ->value('parent_code');

        return is_string($activityCode) ? $this->chapterCodeForActivity($package, $activityCode) : null;
    }
}
