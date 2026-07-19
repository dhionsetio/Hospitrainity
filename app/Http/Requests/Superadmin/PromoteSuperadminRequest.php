<?php

namespace App\Http\Requests\Superadmin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PromoteSuperadminRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation_email' => User::canonicalEmail($this->input('confirmation_email')),
            'confirmation_role' => trim((string) $this->input('confirmation_role')),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && $this->user()?->can('promoteToSuperadmin', $target) === true;
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'expected_role' => ['required', Rule::enum(UserRole::class)],
            'confirmation_email' => [
                'required',
                'email',
                Rule::in([$target instanceof User ? $target->email : '']),
            ],
            'confirmation_role' => ['required', Rule::in([UserRole::Superadmin->value])],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->route('user');

            if (! $target instanceof User) {
                return;
            }

            if ($target->isSuperAdmin()) {
                $validator->errors()->add('confirmation_role', __('admin.role_must_change'));
            }

            if (! $target->hasVerifiedEmail()) {
                $validator->errors()->add('confirmation_role', __('admin.elevated_role_requires_verified_email'));
            }
        }];
    }
}
