<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\LearnerTextResponse;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\ProgressAdministrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LearnerProgressController extends Controller
{
    public function __construct(
        private readonly ProgressAdministrationService $progress,
        private readonly InstitutionContext $institutions,
    ) {}

    public function show(Request $request, User $learner): View
    {
        $this->authorize('viewLearnerProgress', $learner);
        $institution = $this->institutions->current($request, $request->user());
        abort_unless($request->user()->hasInstitutionRole($institution, InstitutionRole::InstitutionAdmin), 404);
        $membershipId = $learner->institutionMemberships()
            ->where('institution_id', $institution->getKey())
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->value('id');
        abort_if($membershipId === null, 404);
        $selection = $request->validate([
            'package' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/'],
        ]);

        return view('progress.learner', [
            'detail' => $this->progress->learnerDetail(
                $learner,
                $selection['package'] ?? null,
                $selection['version'] ?? null,
                (int) $membershipId,
            ),
            'administrationRoutePrefix' => 'supervisor',
            'detailRouteName' => 'supervisor.progress.learners.show',
            'backRouteName' => 'supervisor.dashboard',
            'scopeInstitution' => $institution,
        ]);
    }

    public function showClass(
        Request $request,
        CourseOffering $offering,
        CourseEnrollment $enrollment,
    ): View {
        $institution = $this->institutions->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        abort_unless($enrollment->course_offering_id === $offering->getKey(), 404);
        Gate::authorize('view', $offering);

        $learner = User::query()
            ->whereHas('institutionMemberships', fn ($query) => $query->whereKey($enrollment->institution_membership_id))
            ->first();
        abort_unless($learner instanceof User, 404);
        $revision = CourseRevision::query()->find($offering->course_revision_id);
        abort_unless($revision instanceof CourseRevision, 404);
        $package = CurriculumPackage::query()->find($revision->curriculum_package_id);
        abort_if($package === null, 404);
        $allowedChapterCodes = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'chapter')
            ->whereHas('courseRevisionModules', fn ($query) => $query->where('course_revision_id', $revision->getKey()))
            ->pluck('code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->values()
            ->all();

        $selection = $request->validate([
            'package' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/'],
        ]);

        return view('progress.learner', [
            'detail' => $this->progress->learnerDetail(
                $learner,
                $selection['package'] ?? null,
                $selection['version'] ?? null,
                (int) $enrollment->institution_membership_id,
                (string) $offering->getKey(),
                (int) $enrollment->getKey(),
                (int) $package->getKey(),
                $allowedChapterCodes,
            ),
            'administrationRoutePrefix' => 'supervisor',
            'detailRouteName' => 'supervisor.classes.enrollments.progress',
            'detailRouteParameters' => ['offering' => $offering, 'enrollment' => $enrollment],
            'backUrl' => route('supervisor.classes.show', $offering).'#roster-heading',
            'scopeInstitution' => $institution,
            'scopeClass' => $offering,
            'submittedResponses' => LearnerTextResponse::query()
                ->where('user_id', $learner->getKey())
                ->where('course_offering_id', $offering->getKey())
                ->where('course_enrollment_id', $enrollment->getKey())
                ->where('kind', 'assessment')
                ->where('state', 'submitted')
                ->with(['activity', 'prompt'])
                ->latest('submitted_at')
                ->get(),
        ]);
    }
}
