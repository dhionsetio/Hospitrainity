<?php

namespace App\Http\Controllers;

use App\Enums\CurriculumBlockType;
use App\Enums\CurriculumDraftEntityType;
use App\Enums\CurriculumDraftStatus;
use App\Http\Requests\CurriculumDraftActionRequest;
use App\Http\Requests\StoreCurriculumDraftBlockRequest;
use App\Http\Requests\StoreCurriculumDraftEntityRequest;
use App\Http\Requests\StoreCurriculumDraftRequest;
use App\Http\Requests\UpdateCurriculumDraftBlockRequest;
use App\Http\Requests\UpdateCurriculumDraftEntityRequest;
use App\Models\CurriculumAsset;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftBlock;
use App\Models\CurriculumDraftEntity;
use App\Services\Curriculum\CurriculumDraftReview;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurriculumDraftController extends Controller
{
    public function __construct(
        private readonly CurriculumDraftWorkspace $workspace,
        private readonly CurriculumDraftReview $review,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CurriculumDraft::class);
        $drafts = CurriculumDraft::query()->with(['basePackage:id,content_version', 'publishedPackage:id,content_version'])
            ->withCount(['entities', 'blocks'])->latest('updated_at')->paginate(20);

        return view('superadmin.curriculum-drafts.index', [
            'drafts' => $drafts,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(StoreCurriculumDraftRequest $request): RedirectResponse
    {
        $draft = $this->workspace->create($request->user(), $request->validated());

        return redirect()->route($this->routePrefix($request).'.curriculum-drafts.show', $draft)
            ->with('success', __('admin.draft_created'));
    }

    public function show(Request $request, CurriculumDraft $curriculumDraft): View
    {
        $this->authorize('view', $curriculumDraft);
        $curriculumDraft->load([
            'basePackage:id,package_name,content_version,source_tree_sha256',
            'publishedPackage:id,package_name,content_version,source_tree_sha256,is_active',
            'events' => fn ($query) => $query->with('actor:id,name')->latest('id')->limit(30),
            'imports' => fn ($query) => $query->latest('id')->limit(10),
            'assets' => fn ($query) => $query->with('blob')->latest('id'),
        ]);
        $entities = CurriculumDraftEntity::query()->where('curriculum_draft_id', $curriculumDraft->id)
            ->whereIn('entity_type', CurriculumDraftEntityType::values())
            ->withCount(['blocks', 'blocks as available_blocks_count' => fn ($query) => $query->whereNull('archived_at')])
            ->orderBy('entity_type')->orderBy('position')->orderBy('id')->get();

        return view('superadmin.curriculum-drafts.show', [
            'draft' => $curriculumDraft,
            'chapters' => $entities->where('entity_type', 'chapter')->sortBy('position')->values(),
            'sectionsByChapter' => $entities->where('entity_type', 'lesson-section')->groupBy('parent_code'),
            'outcomes' => $entities->where('entity_type', 'outcome')->sortBy('position')->values(),
            'entityTypes' => CurriculumDraftEntityType::cases(),
            'blockTypes' => CurriculumBlockType::cases(),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function editEntity(Request $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): View
    {
        $this->authorize('view', $curriculumDraft);
        abort_unless($draftEntity->curriculum_draft_id === $curriculumDraft->id, 404);
        abort_unless(in_array($draftEntity->entity_type, CurriculumDraftEntityType::values(), true), 404);
        $draftEntity->load(['blocks' => fn ($query) => $query->with('asset.blob')->orderBy('position')->orderBy('id')]);
        $chapters = CurriculumDraftEntity::query()->where('curriculum_draft_id', $curriculumDraft->id)
            ->where('entity_type', 'chapter')->whereNull('archived_at')->orderBy('position')->get();

        return view('superadmin.curriculum-drafts.entity', [
            'draft' => $curriculumDraft,
            'entity' => $draftEntity,
            'chapters' => $chapters,
            'blockTypes' => CurriculumBlockType::cases(),
            'assets' => $curriculumDraft->assets()->with('blob')->whereNull('archived_at')->orderBy('display_name')->get(),
            'activities' => $curriculumDraft->entities()->where('entity_type', 'activity')->whereNull('archived_at')->orderBy('code')->get(),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function storeEntity(StoreCurriculumDraftEntityRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->workspace->createEntity($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'), $request->validated());

        return back()->with('success', __('admin.draft_entity_created'));
    }

    public function updateEntity(UpdateCurriculumDraftEntityRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): RedirectResponse
    {
        $this->workspace->updateEntity(
            $curriculumDraft, $draftEntity, $request->user(),
            (int) $request->validated('draft_revision'), (int) $request->validated('entity_revision'), $request->validated(),
        );

        return back()->with('success', __('admin.draft_entity_saved'));
    }

    public function moveEntity(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->moveEntity($curriculumDraft, $draftEntity, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('entity_revision'), (int) $request->validated('position'));

        return back()->with('success', __('admin.draft_entity_reordered'));
    }

    public function archiveEntity(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->archiveEntity($curriculumDraft, $draftEntity, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('entity_revision'));

        return back()->with('success', __('admin.draft_entity_archived'));
    }

    public function restoreEntity(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->restoreEntity($curriculumDraft, $draftEntity, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('entity_revision'));

        return back()->with('success', __('admin.draft_entity_restored'));
    }

    public function storeBlock(StoreCurriculumDraftBlockRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftEntity $draftEntity): RedirectResponse
    {
        $payload = $request->payload();
        $asset = $this->assetFor($request, $curriculumDraft);
        $this->workspace->createBlock(
            $curriculumDraft, $draftEntity, $request->user(),
            (int) $request->validated('draft_revision'), (int) $request->validated('entity_revision'),
            CurriculumBlockType::from($request->validated('block_type')), $payload, $asset,
        );

        return back()->with('success', __('admin.draft_block_created'));
    }

    public function updateBlock(UpdateCurriculumDraftBlockRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftBlock $draftBlock): RedirectResponse
    {
        $payload = $request->payload();
        $asset = $this->assetFor($request, $curriculumDraft);
        $this->workspace->updateBlock(
            $curriculumDraft, $draftBlock, $request->user(),
            (int) $request->validated('draft_revision'), (int) $request->validated('block_revision'), $payload, $asset,
        );

        return back()->with('success', __('admin.draft_block_saved'));
    }

    public function moveBlock(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftBlock $draftBlock): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->moveBlock($curriculumDraft, $draftBlock, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('block_revision'), (int) $request->validated('position'));

        return back()->with('success', __('admin.draft_block_reordered'));
    }

    public function archiveBlock(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftBlock $draftBlock): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->setBlockArchived($curriculumDraft, $draftBlock, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('block_revision'), true);

        return back()->with('success', __('admin.draft_block_archived'));
    }

    public function restoreBlock(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft, CurriculumDraftBlock $draftBlock): RedirectResponse
    {
        $this->authorize('update', $curriculumDraft);
        $this->workspace->setBlockArchived($curriculumDraft, $draftBlock, $request->user(), (int) $request->validated('draft_revision'), (int) $request->validated('block_revision'), false);

        return back()->with('success', __('admin.draft_block_restored'));
    }

    public function validateDraft(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->authorize('validate', $curriculumDraft);
        $draft = $this->review->validate($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'));

        return back()->with($draft->status === CurriculumDraftStatus::InReview ? 'success' : 'warning',
            $draft->status === CurriculumDraftStatus::InReview ? __('admin.draft_validation_passed') : __('admin.draft_validation_failed'));
    }

    public function requestChanges(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->authorize('requestChanges', $curriculumDraft);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        $this->review->requestChanges($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'), $reason);

        return back()->with('success', __('admin.draft_changes_requested'));
    }

    public function approve(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->authorize('approve', $curriculumDraft);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        $this->review->approve($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'), $reason);

        return back()->with('success', __('admin.draft_approved'));
    }

    public function publish(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->authorize('publish', $curriculumDraft);
        $result = $this->review->publish($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'));

        return redirect()->route('superadmin.curriculum-drafts.show', $result['draft'])->with('success', __('admin.draft_published'));
    }

    public function rollback(CurriculumDraftActionRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        $this->authorize('rollback', $curriculumDraft);
        $this->review->rollback($curriculumDraft, $request->user(), (int) $request->validated('draft_revision'));

        return back()->with('success', __('admin.publication_rolled_back'));
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'superadmin' : 'admin';
    }

    private function assetFor(Request $request, CurriculumDraft $draft): ?CurriculumAsset
    {
        $publicId = $request->input('asset_public_id');
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return CurriculumAsset::query()->where('curriculum_draft_id', $draft->id)
            ->where('public_id', $publicId)->whereNull('archived_at')->firstOrFail();
    }
}
