<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreExerciseRequest extends ExerciseRequest
{
    public function rules(): array
    {
        $type = $this->effectiveType();

        return array_merge($this->baseRules(), [
            'type' => ['required', Rule::in(self::TYPES)],
        ], $this->contentRules($type));
    }
}
