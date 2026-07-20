<?php

namespace App\Http\Controllers;

use App\Enums\WorkContextRole;
use App\Exceptions\InvitationUnavailableException;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use App\Services\LearningContext;
use App\Services\RoleLandingResolver;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationAcceptanceController extends Controller
{
    public function __construct(private readonly InstitutionInvitationService $invitations) {}

    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitations->findRedeemable($token);
        if ($invitation === null) {
            abort(404);
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'maskedEmail' => $this->invitations->maskedTarget($invitation),
            'token' => $token,
            'authenticatedAccountCanAccept' => $request->user() !== null
                && hash_equals(
                    hash_hmac('sha256', User::canonicalEmail($request->user()->email), (string) config('app.key')),
                    $invitation->target_email_hash,
                ),
        ]);
    }

    public function store(
        Request $request,
        string $token,
        RoleLandingResolver $landing,
        LearningContext $learning,
        WorkContext $work,
    ): RedirectResponse {
        // Resolve token state before validating account fields so invalid,
        // expired, revoked, and replayed tokens share one public response.
        if ($this->invitations->findRedeemable($token) === null) {
            return redirect()->route('invitations.unavailable');
        }

        $newUser = null;
        if ($request->user() === null) {
            $newUser = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
                'terms' => ['accepted'],
            ]);
        }

        try {
            $redeemed = $this->invitations->redeem($token, $request->user(), $newUser);
        } catch (InvitationUnavailableException) {
            return redirect()->route('invitations.unavailable');
        }

        if ($request->user() === null) {
            Auth::login($redeemed['user']);
        }
        $request->session()->regenerate();
        $request->session()->put(InstitutionContext::SESSION_KEY, $redeemed['institution']->getKey());
        $learning->selectInstitution($request, $redeemed['user'], (int) $redeemed['membership']->getKey());
        $work->select($request, $redeemed['user'], WorkContextRole::Learner);

        return redirect()->to($landing->url($redeemed['user']))
            ->with('status', __('Invitation accepted.'));
    }

    public function unavailable(): View
    {
        return view('invitations.unavailable');
    }
}
