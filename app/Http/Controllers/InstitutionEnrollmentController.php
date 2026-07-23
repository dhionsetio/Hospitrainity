<?php

namespace App\Http\Controllers;

use App\Enums\InstitutionMembershipStatus;
use App\Exceptions\JoinCodeUnavailableException;
use App\Models\CourseOffering;
use App\Models\IdentityAudit;
use App\Models\InstitutionMembership;
use App\Services\InstitutionJoinCodeService;
use App\Services\LearningContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function destroy(Request $request, InstitutionMembership $membership, LearningContext $learning): RedirectResponse
    {
        $user = $request->user();

        if ($membership->user_id !== $user->id) {
            abort(403, __('You are not authorized to leave this institution membership.'));
        }

        DB::transaction(function () use ($user, $membership) {
            $membership->forceFill([
                'status' => InstitutionMembershipStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            IdentityAudit::query()->create([
                'actor_user_id' => $user->id,
                'target_user_id' => $user->id,
                'institution_id' => $membership->institution_id,
                'event' => 'institution_membership.self_left',
                'metadata' => [
                    'provenance' => $membership->provenance->value ?? (string) $membership->provenance,
                    'joined_at' => $membership->joined_at?->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });

        $currentContext = $learning->current($request, $user);
        if ($currentContext['membership_id'] === $membership->id) {
            $remainingMemberships = $learning->availableMemberships($user);
            if ($remainingMemberships->isNotEmpty()) {
                $learning->select($request, $user, 'institution', $remainingMemberships->first()->id);
            } else {
                $learning->select($request, $user, 'personal');
            }
        }

        $institutionName = $membership->institution ? $membership->institution->displayName(app()->getLocale()) : __('the institution');

        return redirect()->route('institution-enrollment.index')->with(
            'status',
            __('You have left :institution. Your active context has been updated.', ['institution' => $institutionName])
        );
    }
}
