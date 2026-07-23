<?php

namespace App\Http\Controllers;

use App\Enums\AccountDisableReason;
use App\Models\Completion;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumResponse;
use App\Models\IdentityAudit;
use App\Models\LearnerTextResponse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountSettingsController extends Controller
{
    /**
     * Update user email address and send verification link.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id),
            ],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The password you entered is incorrect.'),
            ]);
        }

        $canonicalEmail = User::canonicalEmail($validated['email']);

        if ($canonicalEmail === $user->email) {
            return redirect()->route('security.index')->with('status', __('The email address is unchanged.'));
        }

        DB::transaction(function () use ($user, $canonicalEmail) {
            $oldEmail = $user->email;
            $user->forceFill([
                'email' => $canonicalEmail,
                'email_verified_at' => null,
            ])->save();

            IdentityAudit::query()->create([
                'target_user_id' => $user->id,
                'event' => 'account.email_changed',
                'metadata' => [
                    'old_email' => $oldEmail,
                    'new_email' => $canonicalEmail,
                ],
                'created_at' => now(),
            ]);

            $user->sendEmailVerificationNotification();
        });

        return redirect()->route('security.index')->with(
            'status',
            __('Your email address has been updated. Please check your new inbox for a verification link.')
        );
    }

    /**
     * Delete learner's personal progress data.
     */
    public function destroyData(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The password you entered is incorrect.'),
            ]);
        }

        DB::transaction(function () use ($user) {
            Completion::query()->where('user_id', $user->id)->delete();
            CurriculumActivityProgress::query()->where('user_id', $user->id)->delete();
            CurriculumAttempt::query()->where('user_id', $user->id)->delete();
            CurriculumResponse::query()->where('user_id', $user->id)->delete();
            LearnerTextResponse::query()->where('user_id', $user->id)->delete();

            IdentityAudit::query()->create([
                'target_user_id' => $user->id,
                'event' => 'account.learning_data_purged',
                'metadata' => ['source' => 'self_service'],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('security.index')->with(
            'status',
            __('All your personal learning data and progress have been deleted.')
        );
    }

    /**
     * Anonymize and disable user account.
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The password you entered is incorrect.'),
            ]);
        }

        DB::transaction(function () use ($user) {
            IdentityAudit::query()->create([
                'target_user_id' => $user->id,
                'event' => 'account.self_deleted',
                'metadata' => [
                    'email' => $user->email,
                    'role' => $user->role->value ?? (string) $user->role,
                ],
                'created_at' => now(),
            ]);

            // Anonymize user details and mark disabled
            $user->forceFill([
                'name' => __('Deleted Account'),
                'first_name' => null,
                'middle_name' => null,
                'last_name' => null,
                'gender' => null,
                'occupation' => null,
                'occupation_other' => null,
                'prefix' => null,
                'instansi' => '',
                'disabled_at' => now(),
                'disabled_reason_code' => AccountDisableReason::PrivacyRequest->value,
            ])->save();
        });

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', __('Your account has been deleted.'));
    }
}
