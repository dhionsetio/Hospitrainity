<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\IdentityAudit;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'scope_acknowledgement' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'instansi' => '',
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'role' => UserRole::Learner,
                'legacy_institution_state' => LegacyInstitutionState::Unresolved,
            ])->save();
            IdentityAudit::query()->create([
                'target_user_id' => $user->getKey(),
                'event' => 'registration.learning_scope_acknowledged',
                'metadata' => ['notice_version' => 'personal-institution-boundary-v1'],
                'created_at' => now(),
            ]);

            return $user;
        }, attempts: 3);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', __('Your personal learning account has been created. Verify your email before joining an institution.'));
    }
}
