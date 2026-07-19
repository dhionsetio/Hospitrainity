<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateExerciseRequest extends ExerciseRequest
{
    public function rules(): array
    {
        $type = $this->effectiveType();
        $exercise = $this->route('exercise');

        return array_merge($this->baseRules(), [
            // Exercise type is immutable after creation because it defines the
            // persisted JSON contract and the editor/renderer used for content.
            'type' => ['sometimes', 'nullable', Rule::in([$exercise->type])],
        ], $this->contentRules($type));
    }
}
