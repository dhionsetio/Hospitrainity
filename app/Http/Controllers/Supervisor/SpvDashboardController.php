<?php

namespace App\Http\Controllers\Supervisor;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CurriculumProgressService;
use App\Services\InstitutionContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SpvDashboardController extends Controller
{
    private const LEARNERS_PER_PAGE = 20;

    public function __construct(
        private readonly CurriculumProgressService $progress,
        private readonly InstitutionContext $institutions,
    ) {}

    public function index(Request $request): View
    {
        $supervisor = Auth::user();
        $institution = $this->institutions->current($request, $supervisor);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->where('role', UserRole::Learner->value)
            ->whereHas('institutionMemberships', function ($query) use ($institution): void {
                $query->where('institution_id', $institution->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value)
                    ->whereHas('roleAssignments', fn ($roleQuery) => $roleQuery
                        ->where('role', InstitutionRole::Learner->value)
                        ->whereNull('revoked_at'));
            })
            ->orderBy('id')
            ->paginate(self::LEARNERS_PER_PAGE)
            ->withQueryString();

        $progressByUser = $this->progress->overallForInstitution($users->getCollection(), $institution);
        $users->getCollection()->each(function (User $user) use ($progressByUser): void {
            $user->overall_progress = $progressByUser[$user->getKey()] ?? 0;
        });

        return view('supervisor.dashboard', compact('users', 'institution'));
    }
}
