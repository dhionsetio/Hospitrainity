<?php

namespace App\Http\Controllers;

use App\Exceptions\JoinCodeUnavailableException;
use App\Models\CourseOffering;
use App\Services\InstitutionJoinCodeService;
use App\Services\LearningContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionEnrollmentController extends Controller
{
    public function index(Request $request, LearningContext $learning): View
    {
        $user = $request->user();

        return view('institution-enrollment.index', [
            'memberships' => $learning->availableMemberships($user),
            'classEnrollments' => $learning->availableClassEnrollments($user),
            'requests' => $user->joinRequests()->with(['institution', 'offering'])->latest('requested_at')->paginate(20),
            'currentContext' => $learning->current($request, $user),
        ]);
    }

    public function store(Request $request, InstitutionJoinCodeService $codes): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'progress_boundary_acknowledgement' => ['accepted'],
        ]);

        try {
            $joinRequest = $codes->requestMembership($request->user(), $validated['code']);
        } catch (JoinCodeUnavailableException) {
            return back()->withErrors([
                'code' => __('The code is invalid, expired, full, or unavailable for this account.'),
            ]);
        }

        $offering = $joinRequest->course_offering_id === null
            ? null
            : CourseOffering::query()->findOrFail($joinRequest->course_offering_id);
        $status = $offering === null
            ? __('Your request to join :institution is pending approval. Your earlier personal progress remains private.', [
                'institution' => $joinRequest->institution->displayName(app()->getLocale()),
            ])
            : __('Your request to join :class is pending approval. Your earlier personal progress remains private.', [
                'class' => $offering->title,
            ]);

        return back()->with('status', $status);
    }
}
