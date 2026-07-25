<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurriculumDraftPreviewAttemptRequest;
use App\Models\CurriculumDraft;
use App\Services\Curriculum\CurriculumAttemptService;
use App\Services\Curriculum\CurriculumDraftPreviewRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CurriculumDraftPreviewController extends Controller
{
    public function __construct(
        private readonly CurriculumDraftPreviewRepository $preview,
        private readonly CurriculumAttemptService $attempts,
    ) {}

    public function index(CurriculumDraft $curriculumDraft): View
    {
        $this->authorize('preview', $curriculumDraft);

        return view('curriculum.dashboard', [
            'chapters' => $this->preview->chapters($curriculumDraft),
            'package' => (object) [
                'content_version' => $curriculumDraft->content_version,
                'projection_meta' => ['notice' => __('admin.preview_does_not_record_progress')],
            ],
            'curriculumPreview' => $curriculumDraft,
            'showCurriculumEvidence' => true,
            'nextAction' => null,
            'learningStreak' => 0,
            'reviewQueue' => [],
            'classAnnouncements' => collect(),
        ]);
    }

    public function chapter(CurriculumDraft $curriculumDraft, string $chapter): View
    {
        $this->authorize('preview', $curriculumDraft);
        $curriculumChapter = $this->preview->chapter($curriculumDraft, $chapter);
        abort_if($curriculumChapter === null, 404);

        return view('curriculum.chapter', compact('curriculumChapter', 'curriculumDraft') + [
            'curriculumPreview' => $curriculumDraft,
            'showCurriculumEvidence' => true,
        ]);
    }

    public function section(CurriculumDraft $curriculumDraft, string $section): View
    {
        $this->authorize('preview', $curriculumDraft);
        $curriculumSection = $this->preview->section($curriculumDraft, $section);
        abort_if($curriculumSection === null, 404);

        return view('curriculum.section', compact('curriculumSection') + [
            'curriculumPreview' => $curriculumDraft,
            'showCurriculumEvidence' => true,
        ]);
    }

    public function activity(CurriculumDraft $curriculumDraft, string $activity): View
    {
        $this->authorize('preview', $curriculumDraft);
        $curriculumActivity = $this->preview->activity($curriculumDraft, $activity);
        abort_if($curriculumActivity === null, 404);

        return view('curriculum.activity', compact('curriculumActivity') + [
            'curriculumPreview' => $curriculumDraft,
            'showCurriculumEvidence' => true,
        ]);
    }

    public function attempt(
        StoreCurriculumDraftPreviewAttemptRequest $request,
        CurriculumDraft $curriculumDraft,
        string $activity,
    ): RedirectResponse {
        $result = $this->attempts->preview($curriculumDraft, $activity, $request->validated());
        $routePrefix = $request->user()->isSuperAdmin() ? 'superadmin' : 'admin';

        return redirect()
            ->to(route($routePrefix.'.curriculum-drafts.preview.activities.show', [$curriculumDraft, $activity]).'#attempt-result')
            ->withInput($request->except(['_token', 'attempt_key']))
            ->with('preview_attempt_result', $result);
    }
}
