<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SecurePassword;
use App\Services\SecurityEventRecorder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Handles "forgot password" and "reset password" using Laravel's native
 * password broker. Relies on the existing password_reset_tokens table and the
 * CanResetPassword trait already provided by Illuminate\Foundation\Auth\User.
 */
class PasswordResetController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink([
            ...$request->only('email'),
            'disabled_at' => null,
        ]);

        // Do not reveal account existence, broker throttling, or mail outcomes.
        // The route limiter independently returns HTTP 429 when it is exceeded.
        return back()->with(
            'status',
            __('If an account exists for that email address, a password reset link will be sent.'),
        );
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => User::canonicalEmail($request->query('email')),
        ]);
    }

    public function reset(Request $request, SecurityEventRecorder $events): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', new SecurePassword([$request->input('email')])],
        ]);

        $status = Password::reset(
            [
                ...$request->only('email', 'password', 'password_confirmation', 'token'),
                'disabled_at' => null,
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                DB::table('sessions')->where('user_id', $user->getKey())->delete();

                event(new PasswordReset($user));
            }
        );

        $events->record('authentication.password_reset', $status === Password::PASSWORD_RESET ? 'allowed' : 'denied', null, $request->input('email'), $request, severity: $status === Password::PASSWORD_RESET ? 'notice' : 'warning');

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
