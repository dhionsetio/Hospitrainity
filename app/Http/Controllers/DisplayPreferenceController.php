<?php

namespace App\Http\Controllers;

use App\Services\RoleLandingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisplayPreferenceController extends Controller
{
    public function edit(Request $request, RoleLandingResolver $landing): View
    {
        return view('preferences.edit', [
            'user' => $request->user(),
            'returnUrl' => $landing->url($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ui_theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'ui_motion' => ['required', Rule::in(['system', 'reduce', 'full'])],
            'ui_text_scale' => ['required', Rule::in(['default', 'large', 'larger'])],
            'ui_high_contrast' => ['nullable', 'boolean'],
            'ui_no_audio' => ['nullable', 'boolean'],
        ]);

        $request->user()->forceFill([
            ...$validated,
            'ui_high_contrast' => $request->boolean('ui_high_contrast'),
            'ui_no_audio' => $request->boolean('ui_no_audio'),
        ])->save();

        return redirect()->route('preferences.edit')->with('status', __('Display preferences saved.'));
    }
}
