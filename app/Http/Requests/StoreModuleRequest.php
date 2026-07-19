<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreModuleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('modules', 'title')],
            'description' => ['required', 'string', 'max:10000'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
