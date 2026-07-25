<?php

namespace App\Http\Controllers;

use App\Rules\SecurePassword;
use App\Services\LocalTesterMfaBypass;
use App\Services\MfaService;
use App\Services\RoleLandingResolver;
use App\Services\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class SecuritySettingsController extends Controller
{
    public function index(
        Request $request,
        TwoFactorAuthenticationProvider $totp,
        RoleLandingResolver $landing,
        LocalTesterMfaBypass $testerMfaBypass,
    ): View {
        $user = $request->user();
        $secret = $user->two_factor_secret === null ? null : Crypt::decryptString($user->two_factor_secret);

        return view('security.index', [
            'passkeys' => $user->passkeys()->latest()->get(),
            'totpPending' => $secret !== null && $user->two_factor_confirmed_at === null,
            'totpSecret' => $user->two_factor_confirmed_at === null ? $secret : null,
            'totpUri' => $secret === null ? null : $totp->qrCodeUrl(config('app.name'), $user->email, $secret),
            'sessions' => DB::table('sessions')->where('user_id', $user->getKey())->orderByDesc('last_activity')->get(),
            'currentSessionId' => $request->session()->getId(),
            'recoveryCodes' => $request->session()->pull('new_mfa_recovery_codes', []),
            'passwordFresh' => time() - (int) $request->session()->get('auth.password_confirmed_at', 0) <= (int) config('auth.password_timeout', 900),
            'localTesterMfaBypass' => $testerMfaBypass->allows($user, $request),
            'returnUrl' => $landing->url($user),
        ]);
    }

    public function beginTotp(Request $request, MfaService $mfa, SecurityEventRecorder $events): RedirectResponse
    {
        $mfa->beginTotp($request->user());
        $events->record('mfa.totp_enrollment_started', 'allowed', $request->user(), $request->user()->email, $request);

        return redirect()->route('security.index')->with('status', __('Authenticator-app setup started. Enter a current code to finish.'));
    }

    public function confirmTotp(Request $request, MfaService $mfa, SecurityEventRecorder $events): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $codes = $mfa->confirmTotp($request->user(), $validated['code']);
        if ($codes === []) {
            throw ValidationException::withMessages(['code' => __('The authenticator code was invalid.')]);
        }
        $request->session()->put(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => 'totp', 'new_mfa_recovery_codes' => $codes]);
        $events->record('mfa.totp_confirmed', 'allowed', $request->user(), $request->user()->email, $request);

        return redirect()->route('security.index')->with('status', __('Authenticator-app MFA is active. Save the one-use recovery codes now.'));
    }

    public function regenerateRecoveryCodes(Request $request, MfaService $mfa, SecurityEventRecorder $events): RedirectResponse
    {
        abort_unless($request->user()->hasConfirmedTotp(), 409);
        $request->session()->put('new_mfa_recovery_codes', $mfa->regenerateRecoveryCodes($request->user()));
        $events->record('mfa.recovery_codes_regenerated', 'allowed', $request->user(), $request->user()->email, $request, severity: 'notice');

        return redirect()->route('security.index')->with('status', __('Previous recovery codes were revoked. Save the new codes now.'));
    }

    public function disableTotp(Request $request, MfaService $mfa, SecurityEventRecorder $events): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $user = $request->user();
        $valid = $mfa->verifyTotp($user, $validated['code']) || $mfa->consumeRecoveryCode($user, $validated['code']);
        if (! $valid) {
            throw ValidationException::withMessages(['code' => __('A current authenticator or unused recovery code is required.')]);
        }
        if ($user->requiresMfa() && ! $user->passkeys()->exists()) {
            throw ValidationException::withMessages(['code' => __('Add a passkey before disabling the only required MFA method.')]);
        }
        $mfa->disableTotp($user);
        $events->record('mfa.totp_disabled', 'allowed', $user, $user->email, $request, severity: 'notice');

        return redirect()->route('security.index')->with('status', __('Authenticator-app MFA and its recovery codes were removed.'));
    }

    public function updatePassword(Request $request, SecurityEventRecorder $events): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', new SecurePassword([$user->name, $user->email])],
        ]);
        Auth::logoutOtherDevices($validated['current_password']);
        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        DB::table('sessions')->where('user_id', $user->getKey())->where('id', '!=', $request->session()->getId())->delete();
        $request->session()->passwordConfirmed();
        $events->record('authentication.password_changed', 'allowed', $user, $user->email, $request, severity: 'notice');

        return redirect()->route('security.index')->with('status', __('Password changed and other sessions revoked.'));
    }

    public function revokeSession(Request $request, string $session, SecurityEventRecorder $events): RedirectResponse
    {
        abort_if(hash_equals($request->session()->getId(), $session), 409, 'Use logout to end the current session.');
        $deleted = DB::table('sessions')->where('id', $session)->where('user_id', $request->user()->getKey())->delete();
        abort_unless($deleted === 1, 404);
        $events->record('session.revoked', 'allowed', $request->user(), $request->user()->email, $request);

        return redirect()->route('security.index')->with('status', __('The selected session was revoked.'));
    }

    public function revokeOtherSessions(Request $request, SecurityEventRecorder $events): RedirectResponse
    {
        $count = DB::table('sessions')->where('user_id', $request->user()->getKey())->where('id', '!=', $request->session()->getId())->delete();
        $events->record('session.others_revoked', 'allowed', $request->user(), $request->user()->email, $request, ['count' => $count]);

        return redirect()->route('security.index')->with('status', __('All other sessions were revoked.'));
    }
}
