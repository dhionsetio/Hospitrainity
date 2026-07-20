<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\InstitutionMembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\ProgressAdministrationService;
use Illuminate\Http\Request;
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
}
