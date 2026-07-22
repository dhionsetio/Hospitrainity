<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\CourseEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Services\CourseAccessService;
use App\Services\InstitutionContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpvDashboardController extends Controller
{
    public function __construct(
        private readonly InstitutionContext $institutions,
        private readonly CourseAccessService $access,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $institution = $this->institutions->current($request, $actor);
        $canCreate = $this->access->canCreateCourse($actor, $institution);
        $classes = CourseOffering::query()
            ->where('institution_id', $institution->getKey())
            ->when(! $canCreate, static fn (Builder $query): Builder => $query->whereHas(
                'teachingAssignments',
                static fn (Builder $assignments): Builder => $assignments
                    ->whereNull('revoked_at')
                    ->whereHas('membership', static fn (Builder $memberships): Builder => $memberships
                        ->where('user_id', $actor->getKey())),
            ))
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
}
