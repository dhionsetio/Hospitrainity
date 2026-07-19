<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurriculumImportRequest extends FormRequest
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
            'declared_purpose' => ['required', 'string', 'min:10', 'max:500'],
            'source' => ['required', 'file', 'max:'.(int) config('curriculum.import.docx_max_kib')],
        ];
    }
}
