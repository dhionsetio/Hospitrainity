<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClassSettings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    use ValidatesClassSettings;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('offering')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['timezone' => $this->input('timezone') ?: null]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'term_label' => ['nullable', 'string', 'max:120'],
            'timezone' => $this->timezoneRules(),
        ];
    }
}
