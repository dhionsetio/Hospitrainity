<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LocalTesterMfaBypass;
use App\Services\RoleLandingResolver;
use App\Services\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly RoleLandingResolver $landingResolver) {}

    /** Display the login form. */
    public function showLoginForm(): View
    {
        return view('login');
    }

    /** Handle a login request. */
    public function login(
        Request $request,
        SecurityEventRecorder $events,
        LocalTesterMfaBypass $testerMfaBypass,
    ): RedirectResponse {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);

        // 1. Validate the submitted credentials.
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Attempt to authenticate the user.
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);
        if ($user instanceof User && ! $user->isDisabled() && $provider->validateCredentials($user, $credentials)) {
            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($credentials['password'])])->save();
            }
            $usesLocalTesterBypass = $testerMfaBypass->allows($user, $request);
            if ($user->hasConfirmedTotp() && ! $usesLocalTesterBypass) {
                $request->session()->regenerate();
                $request->session()->put([
                    'auth.mfa_pending_user_id' => $user->getKey(),
                    'auth.mfa_pending_remember' => $request->boolean('remember'),
                ]);
                $events->record('authentication.password_succeeded', 'mfa_required', null, $user->email, $request, ['next' => 'totp']);

                return redirect()->route('mfa.challenge');
            }

            Auth::login($user, $request->boolean('remember'));
            // Rotate the session identifier after successful authentication.
            $request->session()->regenerate();
            if ($usesLocalTesterBypass) {
                $request->session()->forget('auth.mfa_verified_at');
                $request->session()->put(['auth.mfa_method' => 'local_tester_bypass']);
            } elseif (! $user->requiresMfa()) {
                $request->session()->put(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => 'not_required']);
            }
            $events->record('authentication.password_succeeded', 'allowed', $user, $user->email, $request, [
                'mfa_enrollment_required' => $user->requiresMfa() && ! $user->hasStrongMfa() && ! $usesLocalTesterBypass,
                'local_tester_mfa_bypass' => $usesLocalTesterBypass,
            ]);

            if ($user->requiresMfa() && ! $usesLocalTesterBypass) {
                return redirect()->route('security.index');
            }

            // Stored intended URLs are deliberately ignored until they can be
            // proven same-origin and authorized for this account's role.
            return $this->landingResolver->redirect($user);
        }

        $events->record('authentication.password_failed', 'denied', null, $request->input('email'), $request, severity: 'warning');

        // Return to the login form with a generic authentication error.
        return back()->withErrors([
            'email' => __('The email or password is incorrect.'),
        ])->onlyInput('email');
    }

    /** Handle a logout request. */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login'); // Redirect ke halaman utama setelah logout
    }
}
