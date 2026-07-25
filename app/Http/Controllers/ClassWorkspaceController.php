<?php

namespace App\Http\Controllers;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Http\Requests\CopyClassRequest;
use App\Http\Requests\StoreClassFromRevisionRequest;
use App\Http\Requests\StoreClassWithCourseRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Services\ClassTeachingService;
use App\Services\ClassWorkspaceService;
use App\Services\CourseAccessService;
use App\Services\CourseOfferingLifecycle;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassWorkspaceController extends Controller
{
    public function __construct(
        private readonly InstitutionContext $institutions,
        private readonly CourseAccessService $access,
        private readonly ClassWorkspaceService $workspace,
        private readonly ClassTeachingService $teaching,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $institution = $this->institutions->current($request, $actor);
        $canCreate = $this->access->canCreateCourse($actor, $institution);
        $classes = CourseOffering::query()
            ->where('institution_id', $institution->getKey())
            ->when(! $canCreate, fn (Builder $query) => $this->scopeAssignedTo($query, $actor->getKey()))
            ->with([
                'course:id,title',
                'teachingAssignments' => fn ($query) => $query
                    ->whereNull('revoked_at')
                    ->where('role', 'primary')
                    ->with('membership.user:id,name'),
            ])
            ->withCount([
                'enrollments as active_learners_count' => fn ($query) => $query
                    ->where('status', CourseEnrollmentStatus::Active->value),
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'enrollment_open' THEN 1 WHEN 'draft' THEN 2 WHEN 'closed' THEN 3 ELSE 4 END")
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('supervisor.classes.index', compact('institution', 'classes', 'canCreate'));
    }

    public function create(Request $request): View
    {
        $actor = $request->user();
        $institution = $this->institutions->current($request, $actor);
        Gate::authorize('create', [Course::class, $institution]);
        $activePackage = CurriculumPackage::active();

        return view('supervisor.classes.create', [
            'institution' => $institution,
            'activePackage' => $activePackage,
            'modules' => $activePackage === null ? collect() : CurriculumEntity::query()
                ->where('curriculum_package_id', $activePackage->getKey())
                ->where('entity_type', 'chapter')
                ->where('lifecycle_status', 'published')
                ->orderBy('position')
                ->get(),
            'revisions' => CourseRevision::query()
                ->whereHas('course', fn (Builder $query) => $query
                    ->where('institution_id', $institution->getKey())
                    ->whereNull('archived_at'))
                ->with('course:id,title')
                ->latest('created_at')
                ->get(),
            'instructors' => $this->instructorMemberships($institution->getKey()),
        ]);
    }

    public function storeWithCourse(StoreClassWithCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $institution = $this->institutions->current($request, $request->user());
        $offering = $this->workspace->createWithCourse(
            $request->user(),
            $institution,
            CurriculumPackage::query()->findOrFail($validated['curriculum_package_id']),
            InstitutionMembership::query()->findOrFail($validated['primary_instructor_membership_id']),
            $validated['course_key'],
            $validated['course_title'],
            $validated['course_description'] ?? null,
            $validated['revision_title'],
            $validated['module_ids'],
            $validated['class_key'],
            $validated['class_title'],
            $validated['term_label'] ?? null,
            $validated['timezone'] ?? null,
        );

        return redirect()->route('supervisor.classes.show', $offering)
            ->with('status', __('classes.messages.created'));
    }

    public function storeFromRevision(StoreClassFromRevisionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $institution = $this->institutions->current($request, $request->user());
        $offering = $this->workspace->createFromRevision(
            $request->user(),
            $institution,
            CourseRevision::query()->findOrFail($validated['course_revision_id']),
            InstitutionMembership::query()->findOrFail($validated['primary_instructor_membership_id']),
            $validated['class_key'],
            $validated['class_title'],
            $validated['term_label'] ?? null,
            $validated['timezone'] ?? null,
        );

        return redirect()->route('supervisor.classes.show', $offering)
            ->with('status', __('classes.messages.created'));
    }

    public function show(
        Request $request,
        CourseOffering $offering,
        InstitutionInvitationService $invitations,
    ): View {
        $institution = $this->institutions->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        Gate::authorize('view', $offering);
        $canManage = Gate::allows('update', $offering);
        $offering->load([
            'course:id,title',
            'revision.modules.curriculumEntity:id,code,payload',
            'teachingAssignments' => fn ($query) => $query
                ->whereNull('revoked_at')
                ->with('membership.user:id,name'),
            'joinCodes' => fn ($query) => $query->with('issuer:id,name')->latest()->limit(50),
            'joinRequests' => fn ($query) => $query->with(['user:id,name', 'decidedBy:id,name'])->latest('requested_at')->limit(50),
            'events' => fn ($query) => $query->with('actor:id,name')->latest()->limit(100),
            'revisionEvents' => fn ($query) => $query
                ->with(['fromRevision:id,title,revision_number', 'toRevision:id,title,revision_number', 'actor:id,name'])
                ->latest()
                ->limit(100),
            'announcements' => fn ($query) => $query
                ->with(['module:id,payload', 'createdBy:id,name'])
                ->latest('published_at')
                ->limit(100),
        ]);
        $classInvitations = InstitutionInvitation::query()
            ->where('course_offering_id', $offering->getKey())
            ->with(['issuer:id,name', 'acceptedBy:id,name'])
            ->latest()
            ->limit(50)
            ->get();
        $offering->setRelation('invitations', $classInvitations);
        $enrollments = CourseEnrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->with([
                'membership.user:id,name',
                'events' => fn ($query) => $query->latest()->limit(100),
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'suspended' THEN 1 ELSE 2 END")
            ->orderBy('enrolled_at')
            ->paginate(25, ['*'], 'roster')
            ->withQueryString();
        $maskedInvitationTargets = $classInvitations
            ->mapWithKeys(fn (InstitutionInvitation $invitation): array => [
                $invitation->getKey() => $invitations->maskedTarget($invitation),
            ]);
        $activePackage = CurriculumPackage::active();
        $availableModules = $canManage && $activePackage !== null
            ? CurriculumEntity::query()
                ->where('curriculum_package_id', $activePackage->getKey())
                ->where('entity_type', 'chapter')
                ->where('lifecycle_status', 'published')
                ->orderBy('position')
                ->get()
            : collect();

        return view('supervisor.classes.show', [
            'institution' => $institution,
            'offering' => $offering,
            'enrollments' => $enrollments,
            'maskedInvitationTargets' => $maskedInvitationTargets,
            'canManage' => $canManage,
            'instructors' => $canManage ? $this->instructorMemberships($institution->getKey()) : collect(),
            'availableLearners' => $canManage ? $this->availableLearners($offering) : collect(),
            'transferTargets' => $canManage ? $this->transferTargets($request, $offering) : collect(),
            'transitionTargets' => $canManage ? $this->transitionTargets($offering->status) : [],
            'activePackage' => $activePackage,
            'availableModules' => $availableModules,
            'canReviseContent' => $canManage && $activePackage !== null
                && $this->teaching->canReviseContent($request->user(), $offering),
        ]);
    }

    public function update(UpdateClassRequest $request, CourseOffering $offering): RedirectResponse
    {
        $validated = $request->validated();
        $this->workspace->update(
            $request->user(),
            $offering,
            $validated['title'],
            $validated['term_label'] ?? null,
            $validated['timezone'] ?? null,
        );

        return back()->with('status', __('classes.messages.updated'));
    }

    public function transition(Request $request, CourseOffering $offering, CourseOfferingLifecycle $lifecycle): RedirectResponse
    {
        $validated = $request->validate([
            'expected_status' => ['required', Rule::enum(CourseOfferingStatus::class)],
            'target_status' => ['required', Rule::enum(CourseOfferingStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $lifecycle->transition(
            $request->user(),
            $offering,
            CourseOfferingStatus::from($validated['expected_status']),
            CourseOfferingStatus::from($validated['target_status']),
            $validated['reason'],
        );

        return back()->with('status', __('classes.messages.lifecycle_updated'));
    }

    public function copy(CopyClassRequest $request, CourseOffering $offering): RedirectResponse
    {
        $validated = $request->validated();
        $copy = $this->workspace->copy(
            $request->user(),
            $offering,
            InstitutionMembership::query()->findOrFail($validated['primary_instructor_membership_id']),
            $validated['class_key'],
            $validated['class_title'],
        );

        return redirect()->route('supervisor.classes.show', $copy)
            ->with('status', __('classes.messages.copied'));
    }

    public function preview(Request $request, CourseOffering $offering): View
    {
        $institution = $this->institutions->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        Gate::authorize('view', $offering);

        return view('supervisor.classes.preview', [
            'institution' => $institution,
            'offering' => $offering->load(['course', 'revision.modules.curriculumEntity']),
        ]);
    }

    private function scopeAssignedTo(Builder $query, int|string $userId): Builder
    {
        return $query->whereHas('teachingAssignments', fn (Builder $assignments) => $assignments
            ->whereNull('revoked_at')
            ->whereHas('membership', fn (Builder $memberships) => $memberships
                ->where('user_id', $userId)
                ->where('status', InstitutionMembershipStatus::Active->value)));
    }

    private function instructorMemberships(string $institutionId)
    {
        return InstitutionMembership::query()
            ->where('institution_id', $institutionId)
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->whereHas('roleAssignments', fn (Builder $query) => $query
                ->where('role', InstitutionRole::Instructor->value)
                ->whereNull('revoked_at'))
            ->with('user:id,name')
            ->orderBy('user_id')
            ->limit(100)
            ->get();
    }

    private function availableLearners(CourseOffering $offering)
    {
        return InstitutionMembership::query()
            ->where('institution_id', $offering->institution_id)
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->whereHas('roleAssignments', fn (Builder $query) => $query
                ->where('role', InstitutionRole::Learner->value)
                ->whereNull('revoked_at'))
            ->whereDoesntHave('courseEnrollments', fn (Builder $query) => $query
                ->where('course_offering_id', $offering->getKey()))
            ->with('user:id,name')
            ->orderBy('user_id')
            ->limit(100)
            ->get();
    }

    private function transferTargets(Request $request, CourseOffering $source)
    {
        return CourseOffering::query()
            ->where('institution_id', $source->institution_id)
            ->whereKeyNot($source->getKey())
            ->whereIn('status', [CourseOfferingStatus::EnrollmentOpen->value, CourseOfferingStatus::Active->value])
            ->orderBy('title')
            ->limit(100)
            ->get()
            ->filter(fn (CourseOffering $candidate): bool => $this->access->canManageOffering($request->user(), $candidate))
            ->values();
    }

    /** @return list<CourseOfferingStatus> */
    private function transitionTargets(CourseOfferingStatus $status): array
    {
        return match ($status) {
            CourseOfferingStatus::Draft => [CourseOfferingStatus::EnrollmentOpen, CourseOfferingStatus::Archived],
            CourseOfferingStatus::EnrollmentOpen => [CourseOfferingStatus::Active, CourseOfferingStatus::Closed],
            CourseOfferingStatus::Active => [CourseOfferingStatus::Closed],
            CourseOfferingStatus::Closed => [CourseOfferingStatus::Archived],
            CourseOfferingStatus::Archived => [],
        };
    }
}
