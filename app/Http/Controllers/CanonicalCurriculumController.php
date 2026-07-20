<?php

namespace App\Http\Controllers;

use App\Services\CanonicalCurriculumRepository;
use App\Services\Curriculum\CurriculumAttemptService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CanonicalCurriculumController extends Controller
{
    public function __construct(
        private readonly CanonicalCurriculumRepository $curriculum,
        private readonly CurriculumAttemptService $attempts,
    ) {}

    public function chapter(Request $request, string $chapter): View
    {
        $curriculumChapter = $this->curriculum->chapter($chapter);
        abort_if($curriculumChapter === null, 404);
        $showCurriculumEvidence = $request->user()->isSuperAdmin();

        return view('curriculum.chapter', compact('curriculumChapter', 'showCurriculumEvidence'));
    }

    public function activity(Request $request, string $activity): View
    {
        $this->attempts->markViewed($request->user(), $activity);
        $curriculumActivity = $this->curriculum->activity($activity, $request->user());
        abort_if($curriculumActivity === null, 404);
        $showCurriculumEvidence = $request->user()->isSuperAdmin();

        return view('curriculum.activity', compact('curriculumActivity', 'showCurriculumEvidence'));
    }

    public function confidence(Request $request): View
    {
        $confidenceHistory = $this->attempts->confidenceHistory($request->user());
        $showCurriculumEvidence = $request->user()->isSuperAdmin();

        return view('curriculum.confidence-history', compact('confidenceHistory', 'showCurriculumEvidence'));
    }

    public function section(Request $request, string $section): View
    {
        $curriculumSection = $this->curriculum->section($section);
        abort_if($curriculumSection === null, 404);
        $showCurriculumEvidence = $request->user()->isSuperAdmin();

        return view('curriculum.section', compact('curriculumSection', 'showCurriculumEvidence'));
    }
}
