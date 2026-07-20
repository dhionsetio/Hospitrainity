<?php

namespace App\Http\Controllers;

use App\Services\CanonicalCurriculumRepository;
use App\Services\CurriculumProgressService;
use App\Services\NextActionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CurriculumProgressService $progress,
        private readonly CanonicalCurriculumRepository $canonicalCurriculum,
        private readonly NextActionResolver $nextActions,
    ) {}

    /**
     * Display the learning dashboard with its available modules.
     *
     * Canonical progress is version-bound and comes from curriculum activity
     * progress. The legacy fallback uses eager-loaded relations plus one
     * completions query. Both paths keep query counts independent of the number
     * of displayed modules and lessons.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        if ($this->canonicalCurriculum->isActive()) {
            $chapters = $this->canonicalCurriculum->dashboardChaptersFor($user);
            $package = $this->canonicalCurriculum->activePackage();
            $nextAction = $this->nextActions->learner($request, $user, $chapters);
            $showCurriculumEvidence = $user->isSuperAdmin();

            return view('curriculum.dashboard', compact('chapters', 'package', 'nextAction', 'showCurriculumEvidence'));
        }

        $modules = $this->progress->dashboardModulesFor($user);
        $nextAction = null;

        return view('dashboard', compact('modules', 'nextAction'));
    }
}
