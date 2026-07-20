<?php

namespace App\Http\Controllers;

use App\Enums\WorkContextRole;
use App\Services\RoleLandingResolver;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkContextController extends Controller
{
    public function index(
        Request $request,
        WorkContext $contexts,
        RoleLandingResolver $landing,
    ): View|RedirectResponse {
        if (! $contexts->hasAlternativeRole($request->user())) {
            return $landing->redirect($request->user());
        }

        return view('work-context.index', [
            'contexts' => $contexts->available($request, $request->user()),
            'currentRole' => $contexts->current($request, $request->user()),
            'currentPreview' => $request->session()->get(WorkContext::SESSION_PREVIEW_KEY) === true,
        ]);
    }

    public function store(
        Request $request,
        WorkContext $contexts,
        RoleLandingResolver $landing,
    ): RedirectResponse {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', WorkContextRole::values())],
            'institution_id' => ['nullable', 'uuid'],
            'preview' => ['nullable', 'boolean'],
        ]);
        $contexts->select(
            $request,
            $request->user(),
            WorkContextRole::from($validated['role']),
            $validated['institution_id'] ?? null,
            (bool) ($validated['preview'] ?? false),
        );

        return $landing->redirect($request->user())->with('status', __('Work context changed.'));
    }
}
