<?php

namespace App\Http\Controllers;

use App\Services\CanonicalCurriculumRepository;
use App\Services\CurriculumProgressService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CurriculumProgressService $progress,
        private readonly CanonicalCurriculumRepository $canonicalCurriculum,
    ) {}

    /**
     * Menampilkan halaman dasbor dengan daftar modul.
     *
     * Canonical progress is version-bound and comes from curriculum activity
     * progress. The legacy fallback uses eager-loaded relations plus one
     * completions query. Both paths keep query counts independent of the number
     * of displayed modules and lessons.
     */
    public function index(): View
    {
        $user = Auth::user();

        if ($this->canonicalCurriculum->isActive()) {
            $chapters = $this->canonicalCurriculum->dashboardChaptersFor($user);
            $package = $this->canonicalCurriculum->activePackage();

            return view('curriculum.dashboard', compact('chapters', 'package'));
        }

        $modules = $this->progress->dashboardModulesFor($user);

        return view('dashboard', compact('modules'));
    }
}
