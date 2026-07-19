<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends AdminFormRequest
{
    public function rules(): array
    {
        /** @var Module $module */
        $module = $this->route('module');

        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('modules', 'title')->ignore($module)],
            'description' => ['required', 'string', 'max:10000'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
