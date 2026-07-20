<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use App\Services\RoleLandingResolver;
use App\Services\SecurityEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.mfa_pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function store(
        Request $request,
        MfaService $mfa,
        SecurityEventRecorder $events,
        RoleLandingResolver $landing,
    ): RedirectResponse {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:40', 'required_without:code'],
        ]);
        $user = User::query()->whereNull('disabled_at')->find((int) $request->session()->get('auth.mfa_pending_user_id'));
        if ($user === null || ! $user->hasConfirmedTotp()) {
            $request->session()->forget(['auth.mfa_pending_user_id', 'auth.mfa_pending_remember']);

            return redirect()->route('login')->withErrors(['email' => __('The sign-in attempt expired. Please try again.')]);
        }

        $valid = filled($validated['code'] ?? null)
            ? $mfa->verifyTotp($user, (string) $validated['code'])
            : $mfa->consumeRecoveryCode($user, (string) $validated['recovery_code']);
        if (! $valid) {
            $events->record('authentication.mfa_failed', 'denied', null, $user->email, $request, severity: 'warning');

            return back()->withErrors(['code' => __('The authentication code is invalid or has already been used.')]);
        }

        $remember = (bool) $request->session()->pull('auth.mfa_pending_remember', false);
        $request->session()->forget('auth.mfa_pending_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => filled($validated['code'] ?? null) ? 'totp' : 'recovery_code']);
        $events->record('authentication.mfa_succeeded', 'allowed', $user, $user->email, $request, [
            'method' => filled($validated['code'] ?? null) ? 'totp' : 'recovery_code',
        ]);

        return $landing->redirect($user);
    }
}
