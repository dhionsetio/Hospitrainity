<?php

namespace App\Http\Controllers;

use App\Models\ClassAnnouncement;
use App\Models\CurriculumEntity;
use App\Services\CanonicalCurriculumRepository;
use App\Services\CurriculumProgressService;
use App\Services\Engagement\LearningStreakService;
use App\Services\Engagement\ReviewQueueService;
use App\Services\LearningContext;
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
        private readonly LearningContext $learningContext,
        private readonly LearningStreakService $streaks,
        private readonly ReviewQueueService $reviews,
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

        if ($this->canonicalCurriculum->isActiveFor($user)) {
            $chapters = $this->canonicalCurriculum->dashboardChaptersFor($user);
            $package = $this->canonicalCurriculum->packageFor($user);
            $nextAction = $this->nextActions->learner($request, $user, $chapters);
            $showCurriculumEvidence = $user->isSuperAdmin();
            $learningContext = $this->learningContext->current($request, $user);
            $learningStreak = $this->streaks->current($user, $learningContext['scope_key']);
            $reviewQueue = $this->reviews->due($user, $learningContext['scope_key'], $package);
            $classAnnouncements = collect();
            if ($learningContext['course_offering_id'] !== null) {
                $visibleModuleCodes = $chapters->pluck('code');
                $classAnnouncements = ClassAnnouncement::query()
                    ->where('course_offering_id', $learningContext['course_offering_id'])
                    ->whereNull('archived_at')
                    ->where('published_at', '<=', now())
                    ->with(['module:id,code,payload', 'createdBy:id,name'])
                    ->latest('published_at')
                    ->limit(20)
                    ->get()
                    ->filter(function (ClassAnnouncement $announcement) use ($visibleModuleCodes): bool {
                        $module = $announcement->module;

                        return $module === null
                            || ($module instanceof CurriculumEntity && $visibleModuleCodes->contains($module->code));
                    })
                    ->values();
            }

            return view('curriculum.dashboard', compact(
                'chapters',
                'package',
                'nextAction',
                'showCurriculumEvidence',
                'learningStreak',
                'reviewQueue',
                'classAnnouncements',
            ));
        }

        $modules = $this->progress->dashboardModulesFor($user);
        $nextAction = null;

        return view('dashboard', compact('modules', 'nextAction'));
    }
}
