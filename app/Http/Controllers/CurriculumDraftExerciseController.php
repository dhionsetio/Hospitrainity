<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurriculumDraftExerciseRequest;
use App\Http\Requests\UpdateCurriculumDraftExerciseRequest;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Services\Curriculum\CanonicalExerciseTemplateRegistry;
use App\Services\Curriculum\CurriculumDraftExerciseWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CurriculumDraftExerciseController extends Controller
{
    public function __construct(
        private readonly CanonicalExerciseTemplateRegistry $registry,
        private readonly CurriculumDraftExerciseWorkspace $workspace,
    ) {}

    public function overview(Request $request): View
    {
        $this->authorize('viewAny', CurriculumDraft::class);
        $drafts = CurriculumDraft::query()
            ->withCount(['entities as exercise_count' => fn ($query) => $query
                ->where('entity_type', 'activity')
                ->whereNotNull('payload->template_type')
                ->whereNull('archived_at')])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.curriculum-drafts.exercises.overview', [
            'drafts' => $drafts,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function index(Request $request, CurriculumDraft $curriculumDraft): View
    {
        $this->authorize('view', $curriculumDraft);
        $exercises = CurriculumDraftEntity::query()->where('curriculum_draft_id', $curriculumDraft->id)
            ->where('entity_type', 'activity')->whereNotNull('payload->template_type')
            ->select('curriculum_draft_entities.*')->selectSub(function ($query): void {
                $query->from('curriculum_draft_entities as prompts')->selectRaw('count(*)')
                    ->whereColumn('prompts.curriculum_draft_id', 'curriculum_draft_entities.curriculum_draft_id')
                    ->whereColumn('prompts.parent_code', 'curriculum_draft_entities.code')
                    ->where('prompts.entity_type', 'prompt-item')->whereNull('prompts.archived_at');
            }, 'prompt_count')
            ->orderBy('parent_code')->orderBy('code')->get();

        return view('superadmin.curriculum-drafts.exercises.index', [
            'draft' => $curriculumDraft,
            'exercises' => $exercises,
            'templates' => $this->registry->all(),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function create(Request $request, CurriculumDraft $curriculumDraft): View
    {
        $this->authorize('update', $curriculumDraft);
        $type = (string) $request->query('template');
        abort_unless($this->registry->has($type) && $this->registry->get($type)['enabled'] === true, 404);
        $emptyItem = ['code' => '', 'stem' => '', 'answer' => '', 'options' => '', 'tokens' => '', 'model_answer' => '', 'feedback' => ''];

        return $this->form($request, $curriculumDraft, $type, null, [
            'draft_revision' => $curriculumDraft->revision,
            'template_type' => $type,
            'code' => '', 'section_code' => '', 'title' => '', 'guidance' => '',
            'provenance_note' => '', 'audio_asset_public_id' => '', 'rubric' => '',
            'items' => array_fill(0, (int) $this->registry->get($type)['cardinality']['minimum'], $emptyItem),
        ]);
    }

    public function store(StoreCurriculumDraftExerciseRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $activity = $this->workspace->create($curriculumDraft, $request->user(), $request->validated());

        return redirect()->route($this->routePrefix($request).'.curriculum-drafts.exercises.edit', [$curriculumDraft, $activity])
            ->with('success', __('admin.exercise_created'));
    }

    public function edit(Request $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftExercise): View
    {
        $this->authorize('view', $curriculumDraft);
        $this->assertExercise($curriculumDraft, $draftExercise);
        $type = (string) ($draftExercise->payload['template_type'] ?? '');
        abort_unless($this->registry->has($type), 404);

        return $this->form($request, $curriculumDraft, $type, $draftExercise, $this->workspace->editorData($curriculumDraft, $draftExercise));
    }

    public function update(UpdateCurriculumDraftExerciseRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftExercise): RedirectResponse
    {
        $this->assertExercise($curriculumDraft, $draftExercise);
        $this->workspace->update($curriculumDraft, $draftExercise, $request->user(), $request->validated());

        return back()->with('success', __('admin.exercise_saved'));
    }

    public function duplicate(Request $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftExercise): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->assertExercise($curriculumDraft, $draftExercise);
        $validated = $request->validate([
            'draft_revision' => ['required', 'integer', 'min:1'],
            'section_code' => ['required', 'string', 'max:120', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
        ]);
        $copy = $this->workspace->duplicate(
            $curriculumDraft, $draftExercise, $request->user(), (int) $validated['draft_revision'], (string) $validated['section_code'],
        );

        return redirect()->route($this->routePrefix($request).'.curriculum-drafts.exercises.edit', [$curriculumDraft, $copy])
            ->with('success', __('admin.exercise_duplicated'));
    }

    /** @param array<string, mixed> $data */
    private function form(Request $request, CurriculumDraft $draft, string $type, ?CurriculumDraftEntity $exercise, array $data): View
    {
        $sections = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'lesson-section')->whereNull('archived_at')
            ->whereNotExists(function ($query) use ($exercise): void {
                $query->select(DB::raw(1))->from('curriculum_draft_entities as activities')
                    ->whereColumn('activities.curriculum_draft_id', 'curriculum_draft_entities.curriculum_draft_id')
                    ->whereColumn('activities.parent_code', 'curriculum_draft_entities.code')
                    ->where('activities.entity_type', 'activity')->whereNull('activities.archived_at');
                if ($exercise !== null) {
                    $query->where('activities.id', '!=', $exercise->id);
                }
            })->orderBy('parent_code')->orderBy('position')->get();
        if ($exercise !== null && ! $sections->contains('code', $exercise->parent_code)) {
            $current = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', $exercise->parent_code)->first();
            if ($current !== null) {
                $sections->prepend($current);
            }
        }
        $audioAssets = $draft->assets()->with('blob')->where('kind', 'audio')->whereNull('archived_at')->orderBy('display_name')->get();

        return view('superadmin.curriculum-drafts.exercises.form', [
            'draft' => $draft, 'exercise' => $exercise, 'templateType' => $type,
            'definition' => $this->registry->get($type), 'formData' => $data,
            'sections' => $sections, 'audioAssets' => $audioAssets,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    private function assertExercise(CurriculumDraft $draft, CurriculumDraftEntity $exercise): void
    {
        abort_unless(
            $exercise->curriculum_draft_id === $draft->id
            && $exercise->entity_type === 'activity'
            && is_string($exercise->payload['template_type'] ?? null),
            404,
        );
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'superadmin' : 'admin';
    }
}
