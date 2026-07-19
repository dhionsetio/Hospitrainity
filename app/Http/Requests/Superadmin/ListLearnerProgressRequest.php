<?php

namespace App\Http\Requests\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLearnerProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewGlobalLearnerProgress', User::class) === true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'module' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'status' => ['sometimes', 'nullable', Rule::in([
                'viewed',
                'started',
                'attempted',
                'self_checked',
                'completed',
            ])],
            'recent' => ['sometimes', 'nullable', Rule::in(['7', '30', '90'])],
        ];
    }
}
