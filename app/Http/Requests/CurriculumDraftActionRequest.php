<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurriculumDraftActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isContentAdministrator() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'entity_revision' => ['sometimes', 'required', 'integer', 'min:1'],
            'block_revision' => ['sometimes', 'required', 'integer', 'min:1'],
            'position' => ['sometimes', 'required', 'integer', 'min:1', 'max:999'],
            'reason' => ['sometimes', 'required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
