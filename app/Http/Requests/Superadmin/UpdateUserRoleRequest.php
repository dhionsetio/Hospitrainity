<?php

namespace App\Http\Requests\Superadmin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && $this->user()?->can('changeRole', $target) === true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::in(array_map(
                    static fn (UserRole $role): string => $role->value,
                    UserRole::assignableWithoutSuperadmin(),
                )),
            ],
            'expected_role' => ['required', Rule::enum(UserRole::class)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->route('user');
            $newRole = UserRole::tryFrom((string) $this->input('role'));

            if (! $target instanceof User || $newRole === null) {
                return;
            }

            if ($target->role === $newRole) {
                $validator->errors()->add('role', __('admin.role_must_change'));
            }

            if ($newRole->isElevated() && ! $target->hasVerifiedEmail()) {
                $validator->errors()->add('role', __('admin.elevated_role_requires_verified_email'));
            }
        }];
    }
}
