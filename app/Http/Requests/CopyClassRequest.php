<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClassSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class CopyClassRequest extends FormRequest
{
    use ValidatesClassSettings;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('offering')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['class_key' => Str::slug((string) $this->input('class_key'))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'class_key' => $this->classKeyRules(),
            'class_title' => ['required', 'string', 'max:180'],
            'primary_instructor_membership_id' => ['required', 'integer', 'exists:institution_memberships,id'],
        ];
    }
}
