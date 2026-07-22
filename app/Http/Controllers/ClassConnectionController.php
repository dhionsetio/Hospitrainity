<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstitutionJoinCodeRequest;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionJoinCode;
use App\Models\InstitutionJoinRequest;
use App\Notifications\InstitutionInvitationNotification;
use App\Services\InstitutionInvitationService;
use App\Services\InstitutionJoinCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ClassConnectionController extends Controller
{
    public function invite(
        Request $request,
        CourseOffering $offering,
        InstitutionInvitationService $invitations,
    ): RedirectResponse {
        Gate::authorize('manageRoster', $offering);
        abort_unless($offering->acceptsEnrollments(), 409);
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);
        $institution = Institution::query()->findOrFail($offering->institution_id);
        $issued = $invitations->issue($request->user(), $institution, $validated['email'], $offering);
        $invitation = $issued['invitation'];
        try {
            Notification::route('mail', $invitation->target_email_ciphertext)
                ->notify(new InstitutionInvitationNotification(
                    $institution,
                    route('invitations.accept', ['token' => $issued['token']]),
                    $invitation->expires_at->toDayDateTimeString(),
                ));
        } catch (Throwable) {
            $invitations->revokeAfterDeliveryFailure($invitation);

            return back()->withErrors(['email' => __('The invitation could not be sent. Try again later.')]);
        }

        return back()->with('status', __('classes.messages.invitation_sent'));
    }

    public function revokeInvitation(
        Request $request,
        CourseOffering $offering,
        InstitutionInvitation $invitation,
        InstitutionInvitationService $invitations,
    ): RedirectResponse {
        abort_unless($invitation->course_offering_id === $offering->getKey(), 404);
        $invitations->revoke($request->user(), $invitation);

        return back()->with('status', __('classes.messages.invitation_revoked'));
    }

    public function issueCode(
        StoreInstitutionJoinCodeRequest $request,
        CourseOffering $offering,
        InstitutionJoinCodeService $codes,
    ): RedirectResponse {
        Gate::authorize('manageRoster', $offering);
        abort_unless($offering->acceptsEnrollments(), 409);
        $institution = Institution::query()->findOrFail($offering->institution_id);
        $validated = $request->validated();
        $issued = $codes->issue(
            $request->user(),
            $institution,
            (int) $validated['use_limit'],
            $request->durationSeconds(),
            $offering,
        );

        return back()
            ->with('status', __('classes.messages.code_created'))
            ->with('issued_join_code', $issued['code']);
    }

    public function revokeCode(
        Request $request,
        CourseOffering $offering,
        InstitutionJoinCode $joinCode,
        InstitutionJoinCodeService $codes,
    ): RedirectResponse {
        abort_unless($joinCode->course_offering_id === $offering->getKey(), 404);
        $codes->revoke($request->user(), $joinCode);

        return back()->with('status', __('classes.messages.code_revoked'));
    }

    public function decideRequest(
        Request $request,
        CourseOffering $offering,
        InstitutionJoinRequest $joinRequest,
        InstitutionJoinCodeService $codes,
    ): RedirectResponse {
        abort_unless($joinRequest->course_offering_id === $offering->getKey(), 404);
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
        ]);
        $codes->decide($request->user(), $joinRequest, $validated['decision'] === 'approve');

        return back()->with('status', $validated['decision'] === 'approve'
            ? __('classes.messages.request_approved')
            : __('classes.messages.request_rejected'));
    }
}
