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
                'context' => ['required', 'string', 'regex:/^(personal|membership:[1-9][0-9]*)$/'],
            ])['context'];
            if ($validatedContext === 'personal') {
                $learning->selectPersonal($request);
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
            'scope' => ['required', 'string', 'in:personal,institution'],
            'membership_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['scope'] === 'personal') {
            $learning->selectPersonal($request);
        } else {
            $request->validate(['membership_id' => ['required', 'integer', 'min:1']]);
            $learning->selectInstitution($request, $request->user(), (int) $validated['membership_id']);
        }

        return back()->with('status', __('Learning context changed.'));
    }
}
