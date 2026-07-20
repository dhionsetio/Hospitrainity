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
     * Menampilkan halaman dasbor dengan daftar modul.
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

            return view('curriculum.dashboard', compact('chapters', 'package', 'nextAction'));
        }

        $modules = $this->progress->dashboardModulesFor($user);
        $nextAction = null;

        return view('dashboard', compact('modules', 'nextAction'));
    }
}
