<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use App\Services\RoleLandingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingService $onboarding, RoleLandingResolver $landing): View
    {
        return view('onboarding.show', [
            'onboarding' => $onboarding->snapshot($request, $request->user()),
            'returnUrl' => $landing->url($request->user()),
        ]);
    }

    public function update(Request $request, OnboardingService $onboarding, RoleLandingResolver $landing): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['advance', 'skip', 'restart'])],
            'step' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);

        $state = match ($validated['action']) {
            'advance' => $onboarding->advance($request, $request->user(), (int) ($validated['step'] ?? -1)),
            'skip' => $onboarding->skip($request, $request->user()),
            'restart' => $onboarding->restart($request, $request->user()),
            default => throw ValidationException::withMessages(['action' => __('Choose a valid onboarding action.')]),
        };

        if (in_array($state->status, ['completed', 'skipped'], true)) {
            return $landing->redirect($request->user())->with('status', $state->status === 'completed'
                ? __('Onboarding completed. You can restart it at any time.')
                : __('Onboarding skipped. You can restart it from your account navigation.'));
        }

        return redirect()->route('onboarding.show')->with('status', __('Onboarding progress saved.'));
    }
}
