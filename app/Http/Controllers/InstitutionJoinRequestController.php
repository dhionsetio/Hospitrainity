<?php

namespace App\Http\Controllers;

use App\Models\InstitutionJoinRequest;
use App\Services\InstitutionContext;
use App\Services\InstitutionJoinCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstitutionJoinRequestController extends Controller
{
    public function update(
        Request $request,
        InstitutionJoinRequest $joinRequest,
        InstitutionContext $context,
        InstitutionJoinCodeService $codes,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);
        $institution = $context->current($request, $request->user());
        abort_unless($joinRequest->institution_id === $institution->getKey(), 404);
        $codes->decide($request->user(), $joinRequest, $validated['decision'] === 'approve');

        return back()->with('status', $validated['decision'] === 'approve'
            ? __('The learner membership has been approved.')
            : __('The membership request has been rejected.'));
    }
}
