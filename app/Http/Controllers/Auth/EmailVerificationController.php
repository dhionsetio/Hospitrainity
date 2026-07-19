<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RoleLandingResolver;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification flow. Uses the MustVerifyEmail trait/notification already
 * bundled with Illuminate\Foundation\Auth\User; the User model now implements
 * the MustVerifyEmail contract so the framework sends & enforces verification.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly RoleLandingResolver $landingResolver) {}

    /** Show the "please verify your email" notice. */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? $this->landingResolver->redirect($request->user())
            : view('auth.verify-email');
    }

    /** Handle the signed verification link. */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return $this->landingResolver
            ->redirect($request->user())
            ->with('status', __('Your email has been verified.'));
    }

    /** Resend the verification email. */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->landingResolver->redirect($request->user());
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
