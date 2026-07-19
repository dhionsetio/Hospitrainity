<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCurriculumAssetRequest;
use App\Models\CurriculumAsset;
use App\Models\CurriculumDraft;
use App\Services\Curriculum\CurriculumAssetWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CurriculumAssetController extends Controller
{
    public function __construct(private readonly CurriculumAssetWorkspace $workspace) {}

    public function store(StoreCurriculumAssetRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        try {
            $this->workspace->store(
                $curriculumDraft,
                $request->user(),
                (int) $request->validated('draft_revision'),
                $request->file('asset'),
                $request->safe()->except(['draft_revision', 'asset']),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['asset' => $exception->getMessage()]);
        }

        return back()->with('success', __('admin.asset_uploaded'));
    }

    public function show(CurriculumDraft $curriculumDraft, CurriculumAsset $curriculumAsset): BinaryFileResponse
    {
        $this->authorize('preview', $curriculumDraft);
        abort_unless($curriculumAsset->curriculum_draft_id === $curriculumDraft->id && $curriculumAsset->archived_at === null, 404);
        $curriculumAsset->loadMissing('blob');
        $disk = Storage::disk((string) config('curriculum.import.disk'));
        abort_unless($disk->exists($curriculumAsset->blob->storage_path), 404);

        return response()->file($disk->path($curriculumAsset->blob->storage_path), [
            'Content-Type' => $curriculumAsset->blob->detected_mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
