<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('curriculumDraft')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'asset' => ['required', 'file', 'max:'.(int) config('curriculum.import.asset_max_kib')],
            'display_name' => ['required', 'string', 'min:2', 'max:160'],
            'accessibility_text' => ['required', 'string', 'min:3', 'max:2000'],
            'rights_basis' => ['required', 'string', 'min:3', 'max:2000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
