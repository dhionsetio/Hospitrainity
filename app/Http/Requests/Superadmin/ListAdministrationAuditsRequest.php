<?php

namespace App\Http\Requests\Superadmin;

use App\Models\AdministrationAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdministrationAuditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AdministrationAudit::class) === true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(['identity', 'curriculum'])],
            'event' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'actor' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
