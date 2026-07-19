<?php

namespace App\Http\Requests\Superadmin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) === true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', Rule::enum(UserRole::class)],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'verification' => ['sometimes', 'nullable', Rule::in(['verified', 'unverified'])],
        ];
    }
}
