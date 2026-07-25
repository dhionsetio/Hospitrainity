<?php

namespace App\Http\Controllers;

use App\Models\ClassAnnouncement;
use App\Models\CourseOffering;
use App\Models\CurriculumPackage;
use App\Services\ClassTeachingService;
use App\Services\InstitutionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class ClassTeachingController extends Controller
{
    public function __construct(
        private readonly InstitutionContext $institutions,
        private readonly ClassTeachingService $teaching,
    ) {}

    public function revise(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->authorizeOffering($request, $offering);
        $validated = $request->validate([
            'curriculum_package_id' => ['required', 'integer', 'exists:curriculum_packages,id'],
            'revision_title' => ['required', 'string', 'max:180'],
            'module_ids' => ['required', 'array', 'min:1', 'max:100'],
            'module_ids.*' => ['required', 'integer', 'distinct', 'exists:curriculum_entities,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->teaching->reviseContent(
                $request->user(),
                $offering,
                CurriculumPackage::query()->findOrFail($validated['curriculum_package_id']),
                $validated['module_ids'],
                $validated['revision_title'],
                $validated['reason'],
            );
        } catch (InvalidArgumentException|RuntimeException) {
            return back()->withInput()->withErrors([
                'content' => __('classes.errors.content_update_unavailable'),
            ]);
        }

        return back()->with('status', __('classes.messages.content_updated'));
    }

    public function announce(Request $request, CourseOffering $offering): RedirectResponse
    {
        $this->authorizeOffering($request, $offering);
        $validated = $request->validate([
            'instruction_title' => ['required', 'string', 'max:180'],
            'instruction_body' => ['required', 'string', 'max:5000'],
            'instruction_module_id' => ['nullable', 'integer', 'exists:curriculum_entities,id'],
        ]);

        try {
            $this->teaching->publishAnnouncement(
                $request->user(),
                $offering,
                $validated['instruction_title'],
                $validated['instruction_body'],
                $validated['instruction_module_id'] ?? null,
            );
        } catch (InvalidArgumentException|RuntimeException) {
            return back()->withInput()->withErrors([
                'announcement' => __('classes.errors.instruction_unavailable'),
            ]);
        }

        return back()->with('status', __('classes.messages.instruction_posted'));
    }

    public function archive(
        Request $request,
        CourseOffering $offering,
        ClassAnnouncement $announcement,
    ): RedirectResponse {
        $this->authorizeOffering($request, $offering);
        abort_unless($announcement->course_offering_id === $offering->getKey(), 404);
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->teaching->archiveAnnouncement(
                $request->user(),
                $offering,
                $announcement,
                $validated['expected_revision'],
            );
        } catch (InvalidArgumentException|RuntimeException) {
            return back()->withErrors([
                'announcement' => __('classes.errors.instruction_unavailable'),
            ]);
        }

        return back()->with('status', __('classes.messages.instruction_archived'));
    }

    private function authorizeOffering(Request $request, CourseOffering $offering): void
    {
        $institution = $this->institutions->current($request, $request->user());
        abort_unless($offering->institution_id === $institution->getKey(), 404);
        Gate::authorize('update', $offering);
    }
}
