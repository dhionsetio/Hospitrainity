<?php

namespace App\Http\Requests;

use App\Enums\WorkContextRole;
use App\Services\WorkContext;
use Illuminate\Foundation\Http\FormRequest;

class AskCourseAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(WorkContext::class)->current($this, $this->user()) === WorkContextRole::Learner;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question' => [
                'required',
                'string',
                'max:'.(int) config('course_assistant.maximum_question_characters', 300),
            ],
        ];
    }
}
