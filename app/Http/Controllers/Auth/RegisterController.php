<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\IdentityAudit;
use App\Models\PolicyAcknowledgement;
use App\Models\User;
use App\Rules\SecurePassword;
use App\Services\PolicyDocumentRegistry;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(PolicyDocumentRegistry $policies): View
    {
        return view('register', [
            'privacyPolicy' => $policies->document('privacy'),
            'termsPolicy' => $policies->document('terms'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => User::canonicalEmail($request->input('email'))]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'rather_not_say'])],
            'occupation' => ['nullable', Rule::in(['student', 'lecturer', 'teacher', 'staff', 'manager', 'other'])],
            'occupation_other' => ['nullable', 'string', 'max:100'],
            'prefix' => ['nullable', Rule::in(['Mr.', 'Mrs.', 'Ms.', 'None'])],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'password' => ['required', 'confirmed', new SecurePassword([$request->input('first_name'), $request->input('email')])],
            'scope_acknowledgement' => ['accepted'],
            'policy_acknowledgement' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $policies = app(PolicyDocumentRegistry::class);
            $fullName = trim(implode(' ', array_filter([
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'] ?? null,
            ])));

            $user = User::query()->create([
                'name' => $fullName,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'occupation_other' => $validated['occupation_other'] ?? null,
                'prefix' => $validated['prefix'] ?? null,
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

            foreach (['privacy', 'terms'] as $type) {
                $document = $policies->document($type);
                PolicyAcknowledgement::query()->create([
                    'user_id' => $user->getKey(),
                    'policy_type' => $type,
                    'locale' => $document['locale'],
                    'version' => $document['version'],
                    'content_sha256' => $document['content_sha256'],
                    'acknowledged_at' => now(),
                    'source' => 'registration',
                ]);
            }

            return $user;
        }, attempts: 3);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', __('Your personal learning account has been created. Verify your email before joining an institution.'));
    }
}
