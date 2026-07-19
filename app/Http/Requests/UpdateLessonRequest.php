<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;

class UpdateLessonRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug((string) $this->input('slug'))]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'slug' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
