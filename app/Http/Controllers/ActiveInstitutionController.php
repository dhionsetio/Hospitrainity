<?php

namespace App\Http\Controllers;

use App\Services\InstitutionContext;
use App\Services\LearningContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveInstitutionController extends Controller
{
    public function __invoke(Request $request, InstitutionContext $context, LearningContext $learning): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'uuid'],
        ]);
        $context->selectById($request, $request->user(), $validated['institution_id']);
        if ($request->user()->isLearner()) {
            $membershipId = $learning->availableMemberships($request->user())
                ->firstWhere('institution_id', $validated['institution_id'])?->getKey();
            if ($membershipId !== null) {
                $learning->selectInstitution($request, $request->user(), (int) $membershipId);
            }
        }

        return back()->with('status', __('Active institution changed.'));
    }
}
