<?php

namespace App\Http\Requests;

class UpdateCurriculumDraftBlockRequest extends StoreCurriculumDraftBlockRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['entity_revision'], $rules['block_type']);
        $rules['block_revision'] = ['required', 'integer', 'min:1'];

        return $rules;
    }
}
