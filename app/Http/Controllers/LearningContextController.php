<?php

namespace App\Http\Controllers;

use App\Services\LearningContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LearningContextController extends Controller
{
    public function __invoke(Request $request, LearningContext $learning): RedirectResponse
    {
        if ($request->filled('context')) {
            $validatedContext = $request->validate([
                'context' => ['required', 'string', 'regex:/^(personal|membership:[1-9][0-9]*|class:[1-9][0-9]*)$/'],
            ])['context'];
            if ($validatedContext === 'personal') {
                $learning->selectPersonal($request);
            } elseif (str_starts_with($validatedContext, 'class:')) {
                $learning->selectClass(
                    $request,
                    $request->user(),
                    (int) substr($validatedContext, strlen('class:')),
                );
            } else {
                $learning->selectInstitution(
                    $request,
                    $request->user(),
                    (int) substr($validatedContext, strlen('membership:')),
                );
            }

            return back()->with('status', __('Learning context changed.'));
        }

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:personal,institution,class'],
            'membership_id' => ['nullable', 'integer', 'min:1'],
            'course_enrollment_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['scope'] === 'personal') {
            $learning->selectPersonal($request);
        } elseif ($validated['scope'] === 'institution') {
            $request->validate(['membership_id' => ['required', 'integer', 'min:1']]);
            $learning->selectInstitution($request, $request->user(), (int) $validated['membership_id']);
        } else {
            $request->validate(['course_enrollment_id' => ['required', 'integer', 'min:1']]);
            $learning->selectClass($request, $request->user(), (int) $validated['course_enrollment_id']);
        }

        return back()->with('status', __('Learning context changed.'));
    }
}
