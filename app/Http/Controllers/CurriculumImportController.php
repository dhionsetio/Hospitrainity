<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptCurriculumImportRequest;
use App\Http\Requests\StoreCurriculumImportRequest;
use App\Models\CurriculumDraft;
use App\Models\CurriculumImport;
use App\Services\Curriculum\CurriculumImportWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CurriculumImportController extends Controller
{
    public function __construct(private readonly CurriculumImportWorkspace $workspace) {}

    public function store(StoreCurriculumImportRequest $request, CurriculumDraft $curriculumDraft): RedirectResponse
    {
        try {
            $this->workspace->queue(
                $curriculumDraft,
                $request->user(),
                (int) $request->validated('draft_revision'),
                $request->file('source'),
                (string) $request->validated('declared_purpose'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['source' => $exception->getMessage()]);
        }

        return back()->with('success', __('admin.docx_import_queued'));
    }

    public function accept(AcceptCurriculumImportRequest $request, CurriculumDraft $curriculumDraft, CurriculumImport $curriculumImport): RedirectResponse
    {
        try {
            $this->workspace->accept(
                $curriculumDraft,
                $curriculumImport,
                $request->user(),
                (int) $request->validated('draft_revision'),
                (int) $request->validated('import_revision'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['confirmation' => $exception->getMessage()]);
        }

        return back()->with('success', __('admin.docx_import_accepted'));
    }
}
